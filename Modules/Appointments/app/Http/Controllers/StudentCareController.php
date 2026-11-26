<?php

namespace Modules\Appointments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Appointments\Enums\AppointmentStatus;
use Modules\Appointments\Enums\SlotMode;
use Modules\Appointments\Http\Requests\BookCareAppointmentRequest;
use Modules\Appointments\Http\Requests\CreateUrgentAlertRequest;
use Modules\Appointments\Http\Requests\SendCareMessageRequest;
use Modules\Appointments\Http\Resources\AvailabilitySlotResource;
use Modules\Appointments\Http\Resources\CareAppointmentResource;
use Modules\Appointments\Http\Resources\CareMessageResource;
use Modules\Appointments\Http\Resources\ProfessionalProfileResource;
use Modules\Appointments\Http\Resources\UrgentAlertResource;
use Modules\Appointments\Models\Appointment;
use Modules\Appointments\Models\AppointmentProfessional;
use Modules\Appointments\Models\AvailabilitySlot;
use Modules\Appointments\Models\ProfessionalProfile;
use Modules\Appointments\Models\UrgentAlert;
use Modules\Appointments\Notifications\AppointmentProposalAcceptedNotification;
use Modules\Appointments\Notifications\NewAppointmentRequestedNotification;
use Modules\Appointments\Notifications\UrgentAlertCreatedNotification;
use Modules\Appointments\Services\AppointmentConversationService;
use Modules\Appointments\Services\ProfessionalProfileService;
use Modules\Auth\Enums\UserRole;
use Modules\Chat\Models\Conversation;
use Modules\Chat\Models\Message;

class StudentCareController extends Controller
{
    public function __construct(
        private readonly AppointmentConversationService $conversations,
        private readonly ProfessionalProfileService $profiles,
    ) {}

    public function professionals(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ], [
            'per_page.integer' => 'Le nombre d’éléments par page doit être un entier.',
            'per_page.min' => 'Le nombre d’éléments par page doit être au moins 1.',
            'per_page.max' => 'Le nombre d’éléments par page ne peut pas dépasser 50.',
        ]);

        $query = ProfessionalProfile::query()
            ->verified()
            ->with('user')
            ->whereHas('user', fn ($user) => $user
                ->where('role', UserRole::Professional->value)
                ->where('is_active', true));

        if ($specialty = $request->query('specialty')) {
            $query->where('specialty', $specialty);
        }

        return ProfessionalProfileResource::collection(
            $query->orderBy('display_name')->paginate(min($request->integer('per_page', 20), 50)),
        );
    }

    public function slots(int $id): AnonymousResourceCollection
    {
        ProfessionalProfile::query()
            ->verified()
            ->where('user_id', $id)
            ->whereHas('user', fn ($user) => $user
                ->where('role', UserRole::Professional->value)
                ->where('is_active', true))
            ->firstOrFail();

        return AvailabilitySlotResource::collection(
            AvailabilitySlot::query()
                ->where('professional_id', $id)
                ->available()
                ->orderBy('starts_at')
                ->get(),
        );
    }

    public function book(BookCareAppointmentRequest $request): JsonResponse
    {
        $student = $request->user();

        $appointment = DB::transaction(function () use ($request, $student): Appointment {
            $slotId = $request->integer('availability_slot_id');
            $slotCandidate = AvailabilitySlot::query()->whereKey($slotId)->firstOrFail();

            $professional = User::query()
                ->whereKey($slotCandidate->professional_id)
                ->where('role', UserRole::Professional->value)
                ->where('is_active', true)
                ->lockForUpdate()
                ->firstOrFail();

            $slot = AvailabilitySlot::query()
                ->whereKey($slotId)
                ->where('professional_id', $professional->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($slot->is_booked || $slot->starts_at->isPast()) {
                abort(422, 'Ce créneau horaire n’est plus disponible.');
            }

            $profile = ProfessionalProfile::query()
                ->verified()
                ->where('user_id', $professional->id)
                ->lockForUpdate()
                ->firstOrFail();

            $legacyProfile = AppointmentProfessional::query()->firstOrCreate(
                ['user_id' => $professional->id],
                [
                    'display_name' => $profile->display_name,
                    'title' => $profile->specialty->label(),
                    'is_available' => true,
                ],
            );
            $this->profiles->syncLegacyProfile($profile);

            $slot->update(['is_booked' => true]);

            return Appointment::query()->create([
                'student_id' => $student->id,
                'professional_id' => $legacyProfile->id,
                'professional_user_id' => $professional->id,
                'availability_slot_id' => $slot->id,
                'scheduled_at' => $slot->starts_at,
                'duration_minutes' => max(1, $slot->starts_at->diffInMinutes($slot->ends_at)),
                'status' => AppointmentStatus::Requested,
                'consultation_type' => $slot->mode instanceof SlotMode ? $slot->mode->value : ($slot->mode ?? 'visio'),
                'reason' => $request->input('reason'),
            ]);
        });

        $appointment->professionalUser?->notify(new NewAppointmentRequestedNotification($appointment));

        return response()->json([
            'message' => 'Votre demande de rendez-vous a été envoyée au professionnel.',
            'appointment' => CareAppointmentResource::make($appointment->load(['professionalProfile', 'professionalUser', 'availabilitySlot'])),
        ], 201);
    }

    public function appointments(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ], [
            'per_page.integer' => 'Le nombre d’éléments par page doit être un entier.',
            'per_page.min' => 'Le nombre d’éléments par page doit être au moins 1.',
            'per_page.max' => 'Le nombre d’éléments par page ne peut pas dépasser 50.',
        ]);

        $appointments = Appointment::query()
            ->with(['student', 'professionalProfile', 'professionalUser', 'availabilitySlot'])
            ->where('student_id', $request->user()->id)
            ->orderBy('scheduled_at', 'desc')
            ->paginate(min($request->integer('per_page', 20), 50));

        return CareAppointmentResource::collection($appointments);
    }

    public function show(Request $request, int $id): CareAppointmentResource
    {
        $appointment = Appointment::query()
            ->with(['student', 'professionalProfile', 'professionalUser', 'availabilitySlot'])
            ->where('student_id', $request->user()->id)
            ->findOrFail($id);

        Gate::authorize('view', $appointment);

        return CareAppointmentResource::make($appointment);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ], [
            'reason.max' => 'Le motif d’annulation ne doit pas dépasser 500 caractères.',
        ]);

        $appointment = DB::transaction(function () use ($request, $id, $data): Appointment {
            $appointment = Appointment::query()
                ->where('student_id', $request->user()->id)
                ->whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            Gate::authorize('cancel', $appointment);

            if (in_array($appointment->status, [AppointmentStatus::Cancelled, AppointmentStatus::Declined, AppointmentStatus::Completed], true)) {
                abort(422, 'Ce rendez-vous ne peut plus être annulé.');
            }

            $appointment->update([
                'status' => AppointmentStatus::Cancelled,
                'cancellation_reason' => $data['reason'] ?? 'Annulé par l’étudiant',
                'cancelled_by' => $request->user()->id,
            ]);

            $appointment->availabilitySlot?->update(['is_booked' => false]);
            $appointment->slot?->update(['is_booked' => false]);

            return $appointment;
        });

        return response()->json([
            'message' => 'Votre rendez-vous a été annulé avec succès.',
            'appointment' => CareAppointmentResource::make($appointment->fresh(['professionalProfile', 'professionalUser', 'availabilitySlot'])),
        ]);
    }

    public function acceptProposal(Request $request, int $id): JsonResponse
    {
        $appointment = DB::transaction(function () use ($request, $id): Appointment {
            $appointment = Appointment::query()
                ->where('student_id', $request->user()->id)
                ->whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            Gate::authorize('view', $appointment);

            if ($appointment->status !== AppointmentStatus::Proposed) {
                abort(422, 'Aucune proposition de créneau n’est en attente pour ce rendez-vous.');
            }

            $appointment->update([
                'status' => AppointmentStatus::Confirmed,
                'responded_at' => now(),
            ]);

            $this->conversations->forAppointment($appointment);

            return $appointment;
        });

        $appointment->professionalUser?->notify(new AppointmentProposalAcceptedNotification($appointment));

        return response()->json([
            'message' => 'Le nouveau créneau proposé a été accepté.',
            'appointment' => CareAppointmentResource::make($appointment->fresh(['professionalProfile', 'professionalUser', 'availabilitySlot'])),
        ]);
    }

    public function messages(Request $request, int $id): AnonymousResourceCollection
    {
        $user = $request->user();
        $appointment = $this->studentAppointment($user, $id);
        Gate::authorize('messages', $appointment);

        $conversation = $this->conversations->forAppointment($appointment);

        Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return CareMessageResource::collection(
            Message::query()
                ->where('conversation_id', $conversation->id)
                ->orderBy('created_at')
                ->get(),
        );
    }

    public function sendMessage(SendCareMessageRequest $request, int $id): JsonResponse
    {
        $user = $request->user();
        $appointment = $this->studentAppointment($user, $id);
        Gate::authorize('messages', $appointment);

        $message = DB::transaction(function () use ($request, $user, $id) {
            $lockedAppointment = Appointment::query()
                ->where('student_id', $user->id)
                ->whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            Gate::authorize('messages', $lockedAppointment);

            if (! $lockedAppointment->isConfirmed()) {
                abort(403, 'Le tchat est fermé après la fin de la séance. Vous pouvez consulter les messages existants.');
            }

            $conversation = $this->conversations->forAppointment($lockedAppointment);
            Conversation::query()->whereKey($conversation->id)->lockForUpdate()->firstOrFail();

            $message = Message::query()->create([
                'conversation_id' => $conversation->id,
                'sender_id' => $user->id,
                'body' => $request->input('body'),
            ]);

            $conversation->update(['last_message_at' => now()]);

            return $message;
        });

        return response()->json([
            'message' => 'Message envoyé.',
            'data' => CareMessageResource::make($message),
        ], 201);
    }

    public function alert(CreateUrgentAlertRequest $request): JsonResponse
    {
        Gate::authorize('create', UrgentAlert::class);

        $alert = UrgentAlert::query()->create([
            'student_id' => $request->user()->id,
            'appointment_id' => $request->input('appointment_id'),
            'message' => $request->input('message'),
            'status' => 'open',
        ]);

        User::query()
            ->where('role', UserRole::Professional->value)
            ->where('is_active', true)
            ->whereHas('professionalCareProfile', fn ($profile) => $profile->verified())
            ->each(fn (User $professional) => $professional->notify(new UrgentAlertCreatedNotification($alert)));

        return response()->json([
            'message' => 'Votre alerte a été transmise à notre équipe d’écoute d’urgence.',
            'alert' => UrgentAlertResource::make($alert),
        ], 201);
    }

    private function studentAppointment(User $user, int $id): Appointment
    {
        return Appointment::query()
            ->with(['professionalProfile', 'professionalUser', 'availabilitySlot'])
            ->where('student_id', $user->id)
            ->findOrFail($id);
    }
}
