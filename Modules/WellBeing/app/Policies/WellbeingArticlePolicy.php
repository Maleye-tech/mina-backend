<?php

namespace Modules\WellBeing\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use Modules\Auth\Enums\UserRole;
use Modules\WellBeing\Models\WellbeingArticle;

class WellbeingArticlePolicy
{
    /**
     * Le catalogue est administré depuis le panneau Filament.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(UserRole::Admin);
    }

    /**
     * Une fiche publiée est visible par tous les utilisateurs autorisés.
     * Un administrateur peut consulter un brouillon depuis l'API.
     */
    public function view(User $user, WellbeingArticle $article): Response
    {
        return $article->is_published || $user->hasRole(UserRole::Admin)
            ? Response::allow()
            : Response::denyAsNotFound('Cette fiche est introuvable.');
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::Admin);
    }

    public function update(User $user, WellbeingArticle $article): bool
    {
        return $user->hasRole(UserRole::Admin);
    }

    public function delete(User $user, WellbeingArticle $article): bool
    {
        return $user->hasRole(UserRole::Admin);
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasRole(UserRole::Admin);
    }
}
