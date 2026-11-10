<?php

namespace Tests\Feature\Auth;

use App\Models\University;
use App\Models\User;
use App\Models\UserConsent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Database\Seeders\AuthOnboardingSeeder;
use Modules\Auth\Enums\UserRole;
use Tests\TestCase;

class StudentProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_update_their_onboarding_profile(): void
    {
        $this->seed(AuthOnboardingSeeder::class);
        $student = User::factory()->create();
        $this->grantPersonalDataConsent($student);
        $token = $student->createToken('student-session', ['full-access']);
        $university = University::where('slug', 'ucad')->firstOrFail();
        $faculty = $university->faculties()->firstOrFail();

        $response = $this->withToken($token->plainTextToken)
            ->patchJson('/api/auth/student-profile', [
                'education_level' => 'Licence 2',
                'study_field' => 'Informatique',
                'faculty_id' => $faculty->id,
                'university_id' => $university->id,
                'role' => UserRole::Admin->value,
            ]);

        $response->assertOk()
            ->assertJsonPath('user.role', UserRole::Student->value)
            ->assertJsonPath('user.student_profile.education_level', 'Licence 2')
            ->assertJsonPath('user.student_profile.study_field', 'Informatique')
            ->assertJsonPath('user.student_profile.faculty', $faculty->name)
            ->assertJsonPath('user.student_profile.university', $university->name)
            ->assertJsonPath('user.student_profile.university_id', $university->id)
            ->assertJsonPath('user.student_profile.faculty_id', $faculty->id);

        $this->assertDatabaseHas('users', [
            'id' => $student->id,
            'education_level' => 'Licence 2',
            'study_field' => 'Informatique',
            'faculty_id' => $faculty->id,
            'university_id' => $university->id,
        ]);

        $this->app['auth']->forgetGuards();
        $this->withToken($token->plainTextToken)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.student_profile.university', $university->name);
    }

    public function test_student_profile_update_changes_only_submitted_fields(): void
    {
        $this->seed(AuthOnboardingSeeder::class);
        $university = University::where('slug', 'ucad')->firstOrFail();
        $student = User::factory()->create([
            'education_level' => 'Licence 1',
            'university_id' => $university->id,
            'university' => $university->name,
        ]);
        $this->grantPersonalDataConsent($student);
        $token = $student->createToken('student-session', ['full-access']);

        $this->withToken($token->plainTextToken)
            ->patchJson('/api/auth/student-profile', [
                'education_level' => 'Licence 2',
            ])
            ->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $student->id,
            'education_level' => 'Licence 2',
            'university_id' => $university->id,
            'university' => $university->name,
        ]);
    }

    public function test_non_student_cannot_update_a_student_profile(): void
    {
        $professional = User::factory()->create(['role' => UserRole::Professional]);
        $token = $professional->createToken('professional-session', ['full-access']);

        $this->withToken($token->plainTextToken)
            ->patchJson('/api/auth/student-profile', [
                'university' => 'Université de Dakar',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $professional->id,
            'university' => null,
        ]);
    }

    public function test_student_profile_rejects_values_longer_than_255_characters(): void
    {
        $student = User::factory()->create();
        $this->grantPersonalDataConsent($student);
        $token = $student->createToken('student-session', ['full-access']);

        $this->withToken($token->plainTextToken)
            ->patchJson('/api/auth/student-profile', [
                'education_level' => str_repeat('a', 256),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['education_level']);

        $this->assertDatabaseHas('users', [
            'id' => $student->id,
            'education_level' => null,
        ]);
    }

    public function test_student_profile_update_requires_authentication(): void
    {
        $this->patchJson('/api/auth/student-profile', [
            'education_level' => 'Licence 2',
        ])->assertUnauthorized();
    }

    private function grantPersonalDataConsent(User $user): void
    {
        $user->consents()->create([
            'consent_type' => UserConsent::PERSONAL_DATA,
            'granted' => true,
            'version' => config('privacy.consents.personal_data.version'),
        ]);
    }
}
