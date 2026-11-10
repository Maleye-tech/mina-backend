<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\UserConsent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_pseudonym_preferences_and_grant_versioned_consents(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('user-session', ['full-access']);

        $response = $this->withToken($token->plainTextToken)
            ->patchJson('/api/auth/settings', [
                'pseudonym' => 'MinaUser_01',
                'preferred_language' => 'en',
                'notifications_enabled' => false,
                'consents' => [
                    'personal_data' => true,
                    'mental_health_data' => true,
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('user.pseudonym', 'MinaUser_01')
            ->assertJsonPath('user.preferences.language', 'en')
            ->assertJsonPath('user.preferences.notifications_enabled', false)
            ->assertJsonPath('user.consents.personal_data.granted', true)
            ->assertJsonPath('user.consents.personal_data.version', config('privacy.consents.personal_data.version'))
            ->assertJsonPath('user.consents.mental_health_data.granted', true);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'pseudonym' => 'MinaUser_01',
            'preferred_language' => 'en',
            'notifications_enabled' => false,
        ]);
        $this->assertDatabaseCount('user_consents', 2);
    }

    public function test_user_cannot_choose_a_pseudonym_already_used_by_another_user(): void
    {
        User::factory()->create(['pseudonym' => 'AlreadyTaken']);
        $user = User::factory()->create();
        $token = $user->createToken('user-session', ['full-access']);

        $this->withToken($token->plainTextToken)
            ->patchJson('/api/auth/settings', [
                'pseudonym' => 'AlreadyTaken',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pseudonym']);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'pseudonym' => null,
        ]);
    }

    public function test_user_can_withdraw_consent_and_the_consent_requiring_feature_is_blocked(): void
    {
        $user = User::factory()->create();
        $user->consents()->create([
            'consent_type' => UserConsent::MENTAL_HEALTH_DATA,
            'granted' => true,
            'version' => config('privacy.consents.mental_health_data.version'),
        ]);
        $token = $user->createToken('user-session', ['full-access']);

        $this->withToken($token->plainTextToken)
            ->getJson('/api/v1/wellbeings')
            ->assertOk();

        $this->app['auth']->forgetGuards();
        $this->withToken($token->plainTextToken)
            ->patchJson('/api/auth/settings', [
                'consents' => ['mental_health_data' => false],
            ])
            ->assertOk()
            ->assertJsonPath('user.consents.mental_health_data.granted', false);

        $this->assertDatabaseCount('user_consents', 2);
        $this->assertDatabaseHas('user_consents', [
            'user_id' => $user->id,
            'consent_type' => UserConsent::MENTAL_HEALTH_DATA,
            'granted' => false,
        ]);

        $this->app['auth']->forgetGuards();
        $this->withToken($token->plainTextToken)
            ->getJson('/api/v1/wellbeings')
            ->assertForbidden();
    }

    public function test_wellbeing_access_requires_a_current_version_of_the_health_data_consent(): void
    {
        $user = User::factory()->create();
        $user->consents()->create([
            'consent_type' => UserConsent::MENTAL_HEALTH_DATA,
            'granted' => true,
            'version' => 'older-version',
        ]);
        $token = $user->createToken('user-session', ['full-access']);

        $this->withToken($token->plainTextToken)
            ->getJson('/api/v1/wellbeings')
            ->assertForbidden();
    }

    public function test_student_profile_updates_require_a_current_personal_data_consent(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('student-session', ['full-access']);

        $this->withToken($token->plainTextToken)
            ->patchJson('/api/auth/student-profile', ['education_level' => 'Licence 1'])
            ->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->withToken($token->plainTextToken)
            ->patchJson('/api/auth/settings', [
                'consents' => ['personal_data' => true],
            ])
            ->assertOk();

        $this->app['auth']->forgetGuards();
        $this->withToken($token->plainTextToken)
            ->patchJson('/api/auth/student-profile', ['education_level' => 'Licence 1'])
            ->assertOk();

        $this->app['auth']->forgetGuards();
        $this->withToken($token->plainTextToken)
            ->patchJson('/api/auth/settings', [
                'consents' => ['personal_data' => false],
            ])
            ->assertOk();

        $this->app['auth']->forgetGuards();
        $this->withToken($token->plainTextToken)
            ->patchJson('/api/auth/student-profile', ['education_level' => 'Licence 2'])
            ->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'education_level' => 'Licence 1',
        ]);
    }

    public function test_settings_reject_invalid_languages_and_unsupported_consent_keys(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('user-session', ['full-access']);

        $this->withToken($token->plainTextToken)
            ->patchJson('/api/auth/settings', [
                'preferred_language' => 'es',
                'consents' => ['unrelated_data' => true],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['preferred_language', 'consents']);

        $this->assertDatabaseCount('user_consents', 0);
    }

    public function test_settings_update_requires_authentication(): void
    {
        $this->patchJson('/api/auth/settings', [
            'preferred_language' => 'fr',
        ])->assertUnauthorized();
    }
}
