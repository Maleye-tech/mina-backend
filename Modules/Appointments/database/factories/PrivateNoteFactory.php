<?php

namespace Modules\Appointments\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Appointments\Models\PrivateNote;

/**
 * @extends Factory<PrivateNote>
 */
#[UseModel(PrivateNote::class)]
class PrivateNoteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'appointment_id' => null,
            'conversation_id' => null,
            'body' => fake()->paragraph(),
        ];
    }
}
