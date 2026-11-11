<?php

namespace Tests\Feature\WellBeing\Concerns;

use App\Models\User;
use App\Models\UserConsent;
use Modules\Auth\Enums\UserRole;

trait InteractsWithWellBeing
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function userWithConsent(UserRole $role = UserRole::Student, array $attributes = []): User
    {
        $user = User::factory()->create(array_merge(['role' => $role], $attributes));

        $user->consents()->create([
            'consent_type' => UserConsent::MENTAL_HEALTH_DATA,
            'granted' => true,
            'version' => config('privacy.consents.mental_health_data.version'),
        ]);

        return $user;
    }

    /**
     * @param  array<int, string>  $abilities
     */
    protected function tokenFor(User $user, array $abilities = ['full-access']): string
    {
        return $user->createToken('wellbeing-test', $abilities)->plainTextToken;
    }
}
