<?php

namespace Tests\Feature\WellBeing;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Enums\UserRole;
use Modules\WellBeing\Filament\Resources\UserTestResultResource;
use Modules\WellBeing\Models\AssessmentQuestion;
use Modules\WellBeing\Models\AssessmentTest;
use Modules\WellBeing\Models\EmergencyContact;
use Modules\WellBeing\Models\UserTestResult;
use Modules\WellBeing\Models\WellbeingArticle;
use Tests\TestCase;

class WellBeingFilamentResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_access_each_wellbeing_resource(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'two_factor_secret' => 'test-secret',
        ]);

        foreach ([
            '/admin/wellbeing-articles',
            '/admin/tests',
            '/admin/emergency-contacts',
            '/admin/user-test-results',
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_a_non_admin_cannot_access_the_wellbeing_resources(): void
    {
        $student = User::factory()->create(['role' => UserRole::Student]);

        $this->actingAs($student)
            ->get('/admin/wellbeing-articles')
            ->assertForbidden();
    }

    public function test_an_admin_can_open_the_create_pages(): void
    {
        $admin = $this->filamentAdmin();

        foreach ([
            '/admin/wellbeing-articles/create',
            '/admin/tests/create',
            '/admin/emergency-contacts/create',
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_an_admin_can_open_the_edit_and_view_pages(): void
    {
        $admin = $this->filamentAdmin();

        $article = WellbeingArticle::factory()->create();
        $test = AssessmentTest::factory()->create();
        AssessmentQuestion::factory()->create(['test_id' => $test->id]);
        $contact = EmergencyContact::factory()->create();

        $result = UserTestResult::factory()->create([
            'response_data' => [
                'answers' => [
                    [
                        'question_id' => 1,
                        'question_text' => 'Question ?',
                        'option_key' => 'a',
                        'option_label' => 'Jamais',
                        'points' => 0,
                    ],
                ],
                'max_score' => 3,
                'percentage' => 0,
                'level' => 'low',
            ],
        ]);

        $urls = [
            "/admin/wellbeing-articles/{$article->id}",
            "/admin/wellbeing-articles/{$article->id}/edit",
            "/admin/tests/{$test->id}",
            "/admin/tests/{$test->id}/edit",
            "/admin/emergency-contacts/{$contact->id}",
            "/admin/emergency-contacts/{$contact->id}/edit",
            "/admin/user-test-results/{$result->id}",
        ];

        foreach ($urls as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_the_result_resource_is_read_only(): void
    {
        $this->assertFalse(UserTestResultResource::canCreate());
        $this->assertFalse(UserTestResultResource::canDeleteAny());
    }

    private function filamentAdmin(): User
    {
        return User::factory()->create([
            'role' => UserRole::Admin,
            'two_factor_secret' => 'test-secret',
        ]);
    }
}
