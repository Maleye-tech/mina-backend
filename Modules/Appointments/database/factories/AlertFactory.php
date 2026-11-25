<?php

namespace Modules\Appointments\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Appointments\Models\Alert;

/**
 * @extends Factory<Alert>
 */
#[UseModel(Alert::class)]
class AlertFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => fake()->randomElement(['crisis_keyword', 'system', 'reminder']),
            'severity' => fake()->randomElement(['low', 'medium', 'high', 'critical']),
            'title' => fake()->sentence(),
            'message' => fake()->paragraph(),
            'status' => 'open',
            'subject_type' => null,
            'subject_id' => null,
        ];
    }
}
