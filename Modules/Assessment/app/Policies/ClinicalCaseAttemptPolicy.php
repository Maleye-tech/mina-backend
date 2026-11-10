<?php

namespace Modules\Assessment\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use Modules\Assessment\Models\ClinicalCaseAttempt;
use Modules\Auth\Enums\UserRole;

class ClinicalCaseAttemptPolicy
{
    /**
     * Seule la propriétaire ou le propriétaire de la tentative (ou un
     * administrateur) peut consulter une tentative ; les autres reçoivent un
     * 404 pour ne pas révéler son existence.
     */
    public function view(User $user, ClinicalCaseAttempt $attempt): Response
    {
        return $this->allowOwnerOrAdmin($user, $attempt);
    }

    public function answer(User $user, ClinicalCaseAttempt $attempt): Response
    {
        return $this->allowOwnerOrAdmin($user, $attempt);
    }

    public function finish(User $user, ClinicalCaseAttempt $attempt): Response
    {
        return $this->allowOwnerOrAdmin($user, $attempt);
    }

    private function allowOwnerOrAdmin(User $user, ClinicalCaseAttempt $attempt): Response
    {
        return $attempt->user_id === $user->id || $user->hasRole(UserRole::Admin)
            ? Response::allow()
            : Response::denyAsNotFound('Cette tentative de cas clinique est introuvable.');
    }
}
