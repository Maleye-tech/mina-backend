<?php

namespace Modules\Assessment\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use Modules\Assessment\Enums\ContentStatus;
use Modules\Assessment\Models\Question;
use Modules\Auth\Enums\UserRole;

class QuestionPolicy
{
    /**
     * Les questions publiées sont visibles par tous ; les brouillons par les administrateurs.
     */
    public function view(User $user, Question $question): Response
    {
        if ($question->status === ContentStatus::Published) {
            return Response::allow();
        }

        return $user->hasRole(UserRole::Admin)
            ? Response::allow()
            : Response::denyAsNotFound('Cette question est introuvable.');
    }

    public function create(User $user): Response
    {
        return $user->hasRole(UserRole::Admin)
            ? Response::allow()
            : Response::deny('Seuls les administrateurs peuvent créer des questions.');
    }

    public function update(User $user, Question $question): Response
    {
        return $user->hasRole(UserRole::Admin)
            ? Response::allow()
            : Response::deny('Seuls les administrateurs peuvent modifier les questions.');
    }

    public function delete(User $user, Question $question): Response
    {
        return $user->hasRole(UserRole::Admin)
            ? Response::allow()
            : Response::deny('Seuls les administrateurs peuvent supprimer des questions.');
    }
}
