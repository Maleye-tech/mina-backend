<?php

namespace Modules\Assessment\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use Modules\Assessment\Enums\ContentStatus;
use Modules\Assessment\Models\Flashcard;
use Modules\Auth\Enums\UserRole;

class FlashcardPolicy
{
    /**
     * Les cartes des paquets publiés sont visibles par tous ; les autres
     * uniquement par les administrateurs.
     */
    public function view(User $user, Flashcard $flashcard): Response
    {
        return $this->allowWhenDeckIsPublished($user, $flashcard, 'Cette carte est introuvable.');
    }

    /**
     * Seules les cartes des paquets publiés peuvent être révisées.
     */
    public function review(User $user, Flashcard $flashcard): Response
    {
        return $this->allowWhenDeckIsPublished($user, $flashcard, 'Cette carte est introuvable.');
    }

    public function create(User $user): Response
    {
        return $this->adminOnly($user, 'Seuls les administrateurs peuvent créer des cartes mémoire.');
    }

    public function update(User $user, Flashcard $flashcard): Response
    {
        return $this->adminOnly($user, 'Seuls les administrateurs peuvent modifier des cartes mémoire.');
    }

    public function delete(User $user, Flashcard $flashcard): Response
    {
        return $this->adminOnly($user, 'Seuls les administrateurs peuvent supprimer des cartes mémoire.');
    }

    private function allowWhenDeckIsPublished(User $user, Flashcard $flashcard, string $message): Response
    {
        $deck = $flashcard->deck;

        if ($deck !== null && $deck->status === ContentStatus::Published) {
            return Response::allow();
        }

        return $user->hasRole(UserRole::Admin)
            ? Response::allow()
            : Response::denyAsNotFound($message);
    }

    private function adminOnly(User $user, string $message): Response
    {
        return $user->hasRole(UserRole::Admin)
            ? Response::allow()
            : Response::deny($message);
    }
}
