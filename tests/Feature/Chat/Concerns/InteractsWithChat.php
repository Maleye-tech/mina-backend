<?php

namespace Tests\Feature\Chat\Concerns;

use App\Models\User;
use Modules\Auth\Enums\UserRole;
use Modules\Chat\Models\Conversation;

trait InteractsWithChat
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function userWithRole(UserRole $role, array $attributes = []): User
    {
        return User::factory()->create(array_merge(['role' => $role], $attributes));
    }

    /**
     * @param  array<int, string>  $abilities
     */
    protected function tokenFor(User $user, array $abilities = ['full-access']): string
    {
        return $user->createToken('chat-test', $abilities)->plainTextToken;
    }

    /**
     * Conversation existante entre un étudiant et un professionnel.
     *
     * @return array{0: User, 1: User, 2: Conversation}
     */
    protected function existingConversation(?User $student = null, ?User $professional = null): array
    {
        $student ??= $this->userWithRole(UserRole::Student);
        $professional ??= $this->userWithRole(UserRole::Professional);

        $conversation = Conversation::factory()
            ->between([$student->id, $professional->id])
            ->create();

        return [$student, $professional, $conversation];
    }
}
