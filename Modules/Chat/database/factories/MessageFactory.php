<?php

namespace Modules\Chat\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Chat\Models\Conversation;
use Modules\Chat\Models\Message;

/**
 * @extends Factory<Message>
 */
#[UseModel(Message::class)]
class MessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'sender_id' => User::factory(),
            'body' => fake()->sentence(12),
            'read_at' => null,
        ];
    }

    /**
     * Message déjà lu par son destinataire.
     */
    public function read(): static
    {
        return $this->state(fn (array $attributes): array => [
            'read_at' => now(),
        ]);
    }
}
