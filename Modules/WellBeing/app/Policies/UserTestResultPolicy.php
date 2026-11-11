<?php

namespace Modules\WellBeing\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use Modules\Auth\Enums\UserRole;
use Modules\WellBeing\Models\UserTestResult;

class UserTestResultPolicy
{
    /**
     * L'historique personnel est accessible à tout utilisateur authentifié ;
     * le contrôleur ne renvoie que ses propres résultats.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Un résultat n'est visible que par son propriétaire ou un administrateur.
     * Le refus est rendu comme un 404 pour ne pas révéler l'existence de la
     * donnée de santé d'un autre utilisateur.
     */
    public function view(User $user, UserTestResult $result): Response
    {
        return $result->user_id === $user->id || $user->hasRole(UserRole::Admin)
            ? Response::allow()
            : Response::denyAsNotFound('Ce résultat est introuvable.');
    }

    /**
     * L'historique est strictement en lecture seule.
     */
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, UserTestResult $result): bool
    {
        return false;
    }

    public function delete(User $user, UserTestResult $result): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
