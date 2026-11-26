<?php

namespace Modules\Chat\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use Modules\Appointments\Enums\AppointmentStatus;
use Modules\Auth\Enums\UserRole;
use Modules\Chat\Models\Conversation;

/**
 * Seuls les participants d'une conversation peuvent y accéder.
 *
 * Les non-participants reçoivent un 404 afin de masquer l'existence de
 * conversations qui ne leur appartiennent pas.
 */
class ConversationPolicy
{
    public function viewAny(User $user): Response
    {
        if (! $user->hasRole(UserRole::Professional)) {
            return Response::allow();
        }

        return $this->canUseProfessionalChat($user)
            ? Response::allow()
            : Response::deny('Votre profil doit être actif, vérifié et votre charte acceptée pour utiliser la messagerie.');
    }

    public function view(User $user, Conversation $conversation): Response
    {
        if (! $conversation->hasParticipant($user)) {
            return Response::denyAsNotFound('Cette conversation est introuvable.');
        }

        $appointment = $conversation->appointment;

        if ($user->hasRole(UserRole::Professional) && $appointment === null) {
            return Response::denyAsNotFound('Cette conversation est introuvable.');
        }

        if ($appointment !== null) {
            if ($user->hasRole(UserRole::Professional)) {
                if (! $this->canUseProfessionalChat($user)) {
                    return Response::deny('Votre profil doit être actif, vérifié et votre charte acceptée pour utiliser la messagerie.');
                }

                if ($appointment->professional_user_id !== $user->id) {
                    return Response::denyAsNotFound('Cette conversation est introuvable.');
                }
            } elseif ($appointment->student_id !== $user->id) {
                return Response::denyAsNotFound('Cette conversation est introuvable.');
            }

            if (! in_array($appointment->status, [AppointmentStatus::Confirmed, AppointmentStatus::Completed], true)) {
                return Response::deny('Le tchat n’est accessible que lorsque le rendez-vous est confirmé.');
            }
        }

        return Response::allow();
    }

    /**
     * Envoi d'un message dans la conversation.
     */
    public function send(User $user, Conversation $conversation): Response
    {
        $view = $this->view($user, $conversation);

        if (! $view->allowed()) {
            return $view;
        }

        if ($conversation->appointment !== null && $conversation->appointment->status !== AppointmentStatus::Confirmed) {
            return Response::deny('Le tchat est fermé après la fin de la séance. Vous pouvez consulter les messages existants.');
        }

        return Response::allow();
    }

    private function canUseProfessionalChat(User $user): bool
    {
        $profile = $user->professionalCareProfile;

        return $user->is_active
            && $profile?->is_verified
            && $profile->hasAcceptedCharter();
    }
}
