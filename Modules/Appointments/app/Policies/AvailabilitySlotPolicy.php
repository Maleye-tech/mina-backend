<?php

namespace Modules\Appointments\Policies;

use App\Models\User;
use Modules\Appointments\Models\AvailabilitySlot;
use Modules\Auth\Enums\UserRole;

class AvailabilitySlotPolicy
{
    public function update(User $user, AvailabilitySlot $slot): bool
    {
        if ($slot->is_booked) {
            return false;
        }

        return $user->id === $slot->professional_id || $user->hasRole(UserRole::Admin);
    }

    public function delete(User $user, AvailabilitySlot $slot): bool
    {
        if ($slot->is_booked) {
            return false;
        }

        return $user->id === $slot->professional_id || $user->hasRole(UserRole::Admin);
    }
}
