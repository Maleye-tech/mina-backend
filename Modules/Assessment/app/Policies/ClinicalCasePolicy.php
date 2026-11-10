<?php

namespace Modules\Assessment\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use Modules\Assessment\Enums\ContentStatus;
use Modules\Assessment\Models\ClinicalCase;
use Modules\Auth\Enums\UserRole;

class ClinicalCasePolicy
{
    /**
     * Les cas cliniques publiés sont visibles par tous ; les brouillons par
     * les administrateurs.
     */
    public function view(User $user, ClinicalCase $clinicalCase): Response
    {
        return $clinicalCase->status === ContentStatus::Published || $user->hasRole(UserRole::Admin)
            ? Response::allow()
            : Response::denyAsNotFound('Ce cas clinique est introuvable.');
    }

    public function create(User $user): Response
    {
        return $user->hasRole(UserRole::Admin)
            ? Response::allow()
            : Response::deny('Seuls les administrateurs peuvent créer des cas cliniques.');
    }

    public function update(User $user, ClinicalCase $clinicalCase): Response
    {
        return $user->hasRole(UserRole::Admin)
            ? Response::allow()
            : Response::deny('Seuls les administrateurs peuvent modifier des cas cliniques.');
    }

    public function delete(User $user, ClinicalCase $clinicalCase): Response
    {
        return $user->hasRole(UserRole::Admin)
            ? Response::allow()
            : Response::deny('Seuls les administrateurs peuvent supprimer des cas cliniques.');
    }
}
