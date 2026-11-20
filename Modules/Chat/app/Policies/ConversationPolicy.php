<?php

namespace Modules\Chat\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use Modules\Chat\Models\Conversation;

/**
 * Seuls les participants d'une conversation peuvent y accéder.
 *
 * Les non-participants reçoivent un 404 afin de masquer l'existence de
 * conversations qui ne leur appartiennent pas.
 */
class ConversationPolicy
{
    public function view(User $user, Conversation $conversation): Response
    {
        return $conversation->hasParticipant($user)
            ? Response::allow()
            : Response::denyAsNotFound('Cette conversation est introuvable.');
    }

    /**
     * Envoi d'un message dans la conversation.
     */
    public function send(User $user, Conversation $conversation): Response
    {
        return $this->view($user, $conversation);
    }
}
