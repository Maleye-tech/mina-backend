<?php

namespace Modules\WellBeing\Policies;

use App\Models\User;
use Modules\Auth\Enums\UserRole;
use Modules\WellBeing\Models\AssessmentTest;

/**
 * Les tests d'auto-évaluation sont administrés depuis le panneau Filament.
 */
class AssessmentTestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::Admin);
    }

    public function view(User $user, AssessmentTest $test): bool
    {
        return $user->hasRole(UserRole::Admin);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::Admin);
    }

    public function update(User $user, AssessmentTest $test): bool
    {
        return $user->hasRole(UserRole::Admin);
    }

    public function delete(User $user, AssessmentTest $test): bool
    {
        return $user->hasRole(UserRole::Admin);
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasRole(UserRole::Admin);
    }
}
