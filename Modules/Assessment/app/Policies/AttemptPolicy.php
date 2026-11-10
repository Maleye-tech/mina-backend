<?php

namespace Modules\Assessment\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use Modules\Assessment\Models\Attempt;
use Modules\Auth\Enums\UserRole;

class AttemptPolicy
{
    /**
     * Une session est visible par son propriétaire ou par un administrateur.
     */
    public function view(User $user, Attempt $attempt): Response
    {
        return $this->allowOwnerOrAdmin($user, $attempt);
    }

    /**
     * Le propriétaire ou un administrateur peut répondre dans la session.
     */
    public function answer(User $user, Attempt $attempt): Response
    {
        return $this->allowOwnerOrAdmin($user, $attempt);
    }

    /**
     * Le propriétaire ou un administrateur peut terminer la session.
     */
    public function finish(User $user, Attempt $attempt): Response
    {
        return $this->allowOwnerOrAdmin($user, $attempt);
    }

    private function allowOwnerOrAdmin(User $user, Attempt $attempt): Response
    {
        return $attempt->user_id === $user->id || $user->hasRole(UserRole::Admin)
            ? Response::allow()
            : Response::denyAsNotFound('Cette session est introuvable.');
    }
}
