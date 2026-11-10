<?php

namespace Tests\Feature\Auth;

use App\Models\Faculty;
use App\Models\University;
use App\Models\User;
use App\Models\UserConsent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Database\Seeders\AuthOnboardingSeeder;
use Modules\Auth\Enums\UserRole;
use Tests\TestCase;

class StudentOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_retrieve_the_active_questions_and_partner_universities(): void
    {
        $this->seed(AuthOnboardingSeeder::class);
        $student = User::factory()->create();
        $token = $student->createToken('student-session', ['full-access']);

        $this->withToken($token->plainTextToken)
            ->getJson('/api/auth/onboarding')
            ->assertOk()
            ->assertJsonCount(4, 'questions')
            ->assertJsonCount(8, 'universities')
            ->assertJsonPath('universities.0.name', 'Université Cheikh Anta Diop (UCAD)')
            ->assertJsonPath('universities.0.faculties.0.name', 'Faculté de Médecine, de Pharmacie et d’Odonto-stomatologie (FMPOS)')
            ->assertJsonPath('student_profile.onboarding_completed', false);
    }

    public function test_student_can_complete_onboarding_with_a_faculty_of_the_selected_university(): void
    {
        $this->seed(AuthOnboardingSeeder::class);
        $student = User::factory()->create();
        $this->grantPersonalDataConsent($student);
        $token = $student->createToken('student-session', ['full-access']);
        $university = University::where('slug', 'ucad')->firstOrFail();
        $faculty = $university->faculties()->firstOrFail();

        $response = $this->withToken($token->plainTextToken)
            ->postJson('/api/auth/onboarding', [
                'education_level' => 'Doctorat',
                'study_field' => 'Médecine',
                'university_id' => $university->id,
                'faculty_id' => $faculty->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('user.student_profile.onboarding_completed', true)
            ->assertJsonPath('user.student_profile.university', $university->name)
            ->assertJsonPath('user.student_profile.faculty', $faculty->name);

        $this->assertDatabaseHas('users', [
            'id' => $student->id,
            'education_level' => 'Doctorat',
            'study_field' => 'Médecine',
            'university_id' => $university->id,
            'faculty_id' => $faculty->id,
        ]);
        $this->assertNotNull($student->fresh()->onboarding_completed_at);
    }

    public function test_student_cannot_complete_onboarding_with_a_faculty_from_another_university(): void
    {
        $this->seed(AuthOnboardingSeeder::class);
        $student = User::factory()->create();
        $this->grantPersonalDataConsent($student);
        $token = $student->createToken('student-session', ['full-access']);
        $university = University::where('slug', 'ucad')->firstOrFail();
        $faculty = Faculty::whereHas('university', fn ($query) => $query->where('slug', 'ugb'))->firstOrFail();

        $this->withToken($token->plainTextToken)
            ->postJson('/api/auth/onboarding', [
                'education_level' => 'Doctorat',
                'study_field' => 'Médecine',
                'university_id' => $university->id,
                'faculty_id' => $faculty->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['faculty_id']);

        $this->assertNull($student->fresh()->onboarding_completed_at);
        $this->assertNull($student->fresh()->university_id);
    }

    public function test_student_must_answer_required_active_questions_to_complete_onboarding(): void
    {
        $this->seed(AuthOnboardingSeeder::class);
        $student = User::factory()->create();
        $this->grantPersonalDataConsent($student);
        $token = $student->createToken('student-session', ['full-access']);

        $this->withToken($token->plainTextToken)
            ->postJson('/api/auth/onboarding', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'education_level',
                'study_field',
                'university_id',
                'faculty_id',
            ]);

        $this->assertNull($student->fresh()->onboarding_completed_at);
    }

    public function test_admin_can_configure_onboarding_question_labels_and_required_state(): void
    {
        $this->seed(AuthOnboardingSeeder::class);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $token = $admin->createToken('admin-session', ['full-access']);

        $this->withToken($token->plainTextToken)
            ->patchJson('/api/auth/onboarding/questions/education_level', [
                'label' => 'Quel niveau suivez-vous ?',
                'is_required' => false,
                'sort_order' => 5,
            ])
            ->assertOk()
            ->assertJsonPath('question.label', 'Quel niveau suivez-vous ?')
            ->assertJsonPath('question.is_required', false);

        $this->assertDatabaseHas('onboarding_questions', [
            'key' => 'education_level',
            'label' => 'Quel niveau suivez-vous ?',
            'is_required' => false,
            'sort_order' => 5,
        ]);

        $student = User::factory()->create();
        $studentToken = $student->createToken('student-session', ['full-access']);
        $this->app['auth']->forgetGuards();
        $this->withToken($studentToken->plainTextToken)
            ->getJson('/api/auth/onboarding')
            ->assertOk()
            ->assertJsonPath('questions.3.label', 'Quel niveau suivez-vous ?');
    }

    public function test_non_admin_cannot_configure_onboarding_questions(): void
    {
        $this->seed(AuthOnboardingSeeder::class);
        $student = User::factory()->create();
        $token = $student->createToken('student-session', ['full-access']);

        $this->withToken($token->plainTextToken)
            ->patchJson('/api/auth/onboarding/questions/education_level', [
                'is_required' => false,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('onboarding_questions', [
            'key' => 'education_level',
            'is_required' => true,
        ]);
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
