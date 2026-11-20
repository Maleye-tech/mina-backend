<?php

namespace Modules\Chat\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Auth\Enums\UserRole;
use Modules\Chat\Models\Conversation;

/**
 * @extends Factory<Conversation>
 */
#[UseModel(Conversation::class)]
class ConversationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $student = User::factory()->create(['role' => UserRole::Student]);
        $professional = User::factory()->create(['role' => UserRole::Professional]);

        return [
            'participant_one_id' => min($student->id, $professional->id),
            'participant_two_id' => max($student->id, $professional->id),
            'last_message_at' => null,
        ];
    }

    /**
     * Conversation entre deux utilisateurs précis (identifiants réordonnés).
     *
     * @param  array<int, int>  $userIds
     */
    public function between(array $userIds): static
    {
        $ids = collect($userIds)->sort()->values();

        return $this->state(fn (array $attributes): array => [
            'participant_one_id' => $ids[0],
            'participant_two_id' => $ids[1],
        ]);
    }

    /**
     * Conversation ayant déjà reçu un message.
     */
    public function withLastMessage(): static
    {
        return $this->state(fn (array $attributes): array => [
            'last_message_at' => now(),
        ]);
    }
}
