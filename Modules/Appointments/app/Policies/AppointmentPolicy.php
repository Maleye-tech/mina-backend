<?php

namespace Modules\Appointments\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use Modules\Appointments\Models\Appointment;
use Modules\Auth\Enums\UserRole;

class AppointmentPolicy
{
    public function view(User $user, Appointment $appointment): Response
    {
        if ($user->hasRole(UserRole::Admin) || $this->participates($user, $appointment)) {
            return Response::allow();
        }

        return Response::denyAsNotFound('Ce rendez-vous est introuvable.');
    }

    public function update(User $user, Appointment $appointment): Response
    {
        if ($user->hasRole(UserRole::Admin) || $this->isProfessional($user, $appointment)) {
            return Response::allow();
        }

        return Response::deny('Seul le professionnel peut modifier ce rendez-vous.');
    }

    public function confirm(User $user, Appointment $appointment): Response
    {
        if ($user->hasRole(UserRole::Admin) || $this->isProfessional($user, $appointment)) {
            return Response::allow();
        }

        return Response::deny('Seul le professionnel peut confirmer ce rendez-vous.');
    }

    public function decline(User $user, Appointment $appointment): Response
    {
        if ($user->hasRole(UserRole::Admin) || $this->isProfessional($user, $appointment)) {
            return Response::allow();
        }

        return Response::deny('Seul le professionnel peut refuser ce rendez-vous.');
    }

    public function complete(User $user, Appointment $appointment): Response
    {
        if ($user->hasRole(UserRole::Admin) || $this->isProfessional($user, $appointment)) {
            return Response::allow();
        }

        return Response::deny('Seul le professionnel peut clôturer ce rendez-vous.');
    }

    public function updateMeetingUrl(User $user, Appointment $appointment): Response
    {
        if ($user->hasRole(UserRole::Admin) || $this->isProfessional($user, $appointment)) {
            return Response::allow();
        }

        return Response::deny('Seul le professionnel peut modifier le lien de visioconférence.');
    }

    public function cancel(User $user, Appointment $appointment): Response
    {
        if ($user->hasRole(UserRole::Admin) || $this->participates($user, $appointment)) {
            return Response::allow();
        }

        return Response::deny('Seul l’étudiant ou le professionnel peut annuler ce rendez-vous.');
    }

    public function messages(User $user, Appointment $appointment): Response
    {
        if (! $this->participates($user, $appointment)) {
            return Response::denyAsNotFound('Ce rendez-vous est introuvable.');
        }

        if (! $appointment->isConfirmed() && ! $appointment->isCompleted()) {
            return Response::deny('Le tchat n’est accessible que lorsque le rendez-vous est confirmé.');
        }

        return Response::allow();
    }

    private function participates(User $user, Appointment $appointment): bool
    {
        return $appointment->student_id === $user->id || $this->isProfessional($user, $appointment);
    }

    private function isProfessional(User $user, Appointment $appointment): bool
    {
        return $appointment->professional_user_id === $user->id
            || ($appointment->professional_user_id === null && $appointment->professional?->user_id === $user->id);
    }
}
