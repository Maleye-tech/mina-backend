<?php

namespace Modules\Appointments\Policies;

use App\Models\User;
use Modules\Appointments\Models\UrgentAlert;
use Modules\Auth\Enums\UserRole;

class UrgentAlertPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::Student, UserRole::Professional, UserRole::Admin);
    }

    public function view(User $user, UrgentAlert $alert): bool
    {
        return $user->id === $alert->student_id
            || $user->hasRole(UserRole::Professional, UserRole::Admin);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::Student);
    }

    public function handle(User $user, UrgentAlert $alert): bool
    {
        return $user->hasRole(UserRole::Professional, UserRole::Admin);
    }
}
