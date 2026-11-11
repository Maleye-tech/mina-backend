<?php

namespace Modules\WellBeing\Policies;

use App\Models\User;
use Modules\Auth\Enums\UserRole;
use Modules\WellBeing\Models\EmergencyContact;

/**
 * Les contacts d'urgence sont administrés depuis le panneau Filament.
 */
class EmergencyContactPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::Admin);
    }

    public function view(User $user, EmergencyContact $contact): bool
    {
        return $user->hasRole(UserRole::Admin);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::Admin);
    }

    public function update(User $user, EmergencyContact $contact): bool
    {
        return $user->hasRole(UserRole::Admin);
    }

    public function delete(User $user, EmergencyContact $contact): bool
    {
        return $user->hasRole(UserRole::Admin);
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasRole(UserRole::Admin);
    }
}
