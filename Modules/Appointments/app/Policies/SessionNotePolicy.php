<?php

namespace Modules\Appointments\Policies;

use App\Models\User;
use Modules\Appointments\Models\SessionNote;
use Modules\Auth\Enums\UserRole;

class SessionNotePolicy
{
    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::Professional);
    }

    public function view(User $user, SessionNote $note): bool
    {
        return $user->id === $note->professional_id;
    }

    public function update(User $user, SessionNote $note): bool
    {
        return $user->id === $note->professional_id;
    }
}
