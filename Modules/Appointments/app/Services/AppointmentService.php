<?php

namespace Modules\Appointments\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Appointments\Enums\AppointmentStatus;
use Modules\Appointments\Models\Appointment;
use Modules\Appointments\Models\AppointmentProfessional;
use Modules\Appointments\Models\AppointmentSlot;
use Modules\Auth\Enums\UserRole;

class AppointmentService
{
    public function book(
        User $student,
        int $professionalId,
        int $slotId,
        string $consultationType = 'video',
        ?string $studentNotes = null
    ): Appointment {
        return DB::transaction(function () use ($student, $professionalId, $slotId, $consultationType, $studentNotes): Appointment {
            $slot = AppointmentSlot::query()
                ->where('id', $slotId)
                ->where('professional_id', $professionalId)
                ->lockForUpdate()
                ->first();

            if (! $slot || $slot->is_booked || $slot->starts_at->isPast()) {
                throw ValidationException::withMessages([
                    'slot_id' => ['Ce créneau horaire n\'est plus disponible.'],
                ]);
            }

            $professional = AppointmentProfessional::query()->findOrFail($professionalId);

            $slot->update(['is_booked' => true]);

            return Appointment::create([
                'student_id' => $student->id,
                'professional_id' => $professional->id,
                'slot_id' => $slot->id,
                'scheduled_at' => $slot->starts_at,
                'duration_minutes' => $professional->consultation_duration_minutes,
                'status' => AppointmentStatus::Confirmed,
                'consultation_type' => $consultationType,
                'meeting_link' => $consultationType === 'video' ? 'https://meet.mina.sn/'.uniqid('care-') : null,
                'student_notes' => $studentNotes,
            ]);
        });
    }

    public function confirm(Appointment $appointment, User $user, ?string $meetingLink = null): Appointment
    {
        $appointment->update([
            'status' => AppointmentStatus::Confirmed,
            'meeting_link' => $meetingLink ?: $appointment->meeting_link,
        ]);

        return $appointment;
    }

    public function cancel(Appointment $appointment, User $user, string $reason): Appointment
    {
        if ($appointment->status === AppointmentStatus::Cancelled) {
            return $appointment;
        }

        return DB::transaction(function () use ($appointment, $user, $reason): Appointment {
            if ($appointment->slot) {
                $appointment->slot->update(['is_booked' => false]);
            }

            $appointment->update([
                'status' => AppointmentStatus::Cancelled,
                'cancellation_reason' => $reason,
                'cancelled_by' => $user->id,
            ]);

            return $appointment;
        });
    }

    public function reschedule(Appointment $appointment, User $user, int $newSlotId): Appointment
    {
        return DB::transaction(function () use ($appointment, $newSlotId): Appointment {
            $newSlot = AppointmentSlot::query()
                ->where('id', $newSlotId)
                ->where('professional_id', $appointment->professional_id)
                ->lockForUpdate()
                ->first();

            if (! $newSlot || $newSlot->is_booked || $newSlot->starts_at->isPast()) {
                throw ValidationException::withMessages([
                    'slot_id' => ['Le nouveau créneau choisi n\'est plus disponible.'],
                ]);
            }

            // Free previous slot
            if ($appointment->slot) {
                $appointment->slot->update(['is_booked' => false]);
            }

            $newSlot->update(['is_booked' => true]);

            $appointment->update([
                'slot_id' => $newSlot->id,
                'scheduled_at' => $newSlot->starts_at,
                'status' => AppointmentStatus::Confirmed,
            ]);

            return $appointment;
        });
    }

    public function listForUser(User $user, int $perPage = 15): LengthAwarePaginator
    {
        $query = Appointment::query()->with(['student', 'professional.user', 'slot']);

        if ($user->hasRole(UserRole::Professional)) {
            $pro = AppointmentProfessional::where('user_id', $user->id)->first();
            $query->where('professional_id', $pro?->id ?? 0);
        } else {
            $query->where('student_id', $user->id);
        }

        return $query->orderBy('scheduled_at', 'desc')->paginate($perPage);
    }

    public function listProfessionals(int $perPage = 15): LengthAwarePaginator
    {
        return AppointmentProfessional::query()
            ->available()
            ->with('user')
            ->paginate($perPage);
    }

    public function listAvailableSlots(int $professionalId): Collection
    {
        return AppointmentSlot::query()
            ->where('professional_id', $professionalId)
            ->available()
            ->orderBy('starts_at')
            ->get();
    }
}
