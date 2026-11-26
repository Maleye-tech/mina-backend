<?php

namespace Modules\Appointments\Services;

use Illuminate\Support\Facades\DB;
use Modules\Appointments\Models\Appointment;
use Modules\Chat\Models\Conversation;

class AppointmentConversationService
{
    public function forAppointment(Appointment $appointment): Conversation
    {
        $professionalUserId = $appointment->professional_user_id ?? $appointment->professional?->user_id;

        if ($professionalUserId === null) {
            abort(409, 'Le professionnel de ce rendez-vous est introuvable.');
        }

        $participants = [$appointment->student_id, $professionalUserId];
        sort($participants);

        return DB::transaction(function () use ($appointment, $participants): Conversation {
            $appointment->loadMissing('professional');

            return Conversation::query()->firstOrCreate(
                ['appointment_id' => $appointment->id],
                [
                    'participant_one_id' => $participants[0],
                    'participant_two_id' => $participants[1],
                    'status' => 'active',
                ],
            );
        });
    }
}
