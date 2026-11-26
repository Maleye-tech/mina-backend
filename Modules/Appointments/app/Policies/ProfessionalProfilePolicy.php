<?php

namespace Modules\Appointments\Policies;

use App\Models\User;
use Modules\Appointments\Models\ProfessionalProfile;
use Modules\Auth\Enums\UserRole;

class ProfessionalProfilePolicy
{
    public function viewAny(?User $user): bool
    {
        return $user !== null;
    }

    public function view(?User $user, ProfessionalProfile $profile): bool
    {
        return $profile->is_verified
            || $user?->id === $profile->user_id
            || $user?->hasRole(UserRole::Admin);
    }

    public function update(User $user, ProfessionalProfile $profile): bool
    {
        return $user->id === $profile->user_id || $user->hasRole(UserRole::Admin);
    }

    public function manageAvailability(User $user): bool
    {
        return $user->hasRole(UserRole::Professional)
            && $user->professionalCareProfile?->is_verified === true
            && $user->professionalCareProfile->hasAcceptedCharter();
    }
}
