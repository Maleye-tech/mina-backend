<?php

namespace Modules\Assessment\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use Modules\Assessment\Enums\ContentStatus;
use Modules\Assessment\Models\FlashcardDeck;
use Modules\Auth\Enums\UserRole;

class FlashcardDeckPolicy
{
    /**
     * Les paquets publiés sont visibles par tous ; les brouillons par les administrateurs.
     */
    public function view(User $user, FlashcardDeck $deck): Response
    {
        return $deck->status === ContentStatus::Published || $user->hasRole(UserRole::Admin)
            ? Response::allow()
            : Response::denyAsNotFound('Ce paquet de cartes est introuvable.');
    }

    public function create(User $user): Response
    {
        return $this->adminOnly($user, 'Seuls les administrateurs peuvent créer des paquets de cartes.');
    }

    public function update(User $user, FlashcardDeck $deck): Response
    {
        return $this->adminOnly($user, 'Seuls les administrateurs peuvent modifier des paquets de cartes.');
    }

    public function delete(User $user, FlashcardDeck $deck): Response
    {
        return $this->adminOnly($user, 'Seuls les administrateurs peuvent supprimer des paquets de cartes.');
    }

    private function adminOnly(User $user, string $message): Response
    {
        return $user->hasRole(UserRole::Admin)
            ? Response::allow()
            : Response::deny($message);
    }
}
