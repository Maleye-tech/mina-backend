<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Assessment\Pages\CreateQcmImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Assessment\Enums\ImportStatus;
use Modules\Assessment\Jobs\ProcessQcmImport;
use Modules\Assessment\Models\QcmImport;
use Modules\Auth\Enums\UserRole;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AssessmentResourcesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'role' => UserRole::Admin,
            'two_factor_secret' => (new Google2FA)->generateSecretKey(),
            'two_factor_enabled_at' => now(),
        ]);
    }

    public function test_admin_can_access_the_assessment_resource_pages(): void
    {
        $admin = $this->admin();

        $urls = [
            '/admin/assessment/questions',
            '/admin/assessment/questions/create',
            '/admin/assessment/flashcard-decks',
            '/admin/assessment/flashcard-decks/create',
            '/admin/assessment/clinical-cases',
            '/admin/assessment/clinical-cases/create',
            '/admin/assessment/qcm-imports',
            '/admin/assessment/qcm-imports/create',
            '/admin/library/library-contents',
            '/admin/library/podcasts',
        ];

        foreach ($urls as $url) {
            $this->actingAs($admin)
                ->withSession(['password_hash_web' => $admin->getAuthPassword()])
                ->get($url)
                ->assertOk();
        }
    }

    public function test_guest_is_redirected_to_the_panel_login(): void
    {
        $this->get('/admin/assessment/questions')->assertRedirect();
    }

    public function test_admin_can_launch_a_qcm_import_from_filament(): void
    {
        Queue::fake();
        Storage::fake('local');

        $admin = $this->admin();
        $this->actingAs($admin);

        Livewire::test(CreateQcmImport::class)
            ->set('data.file', UploadedFile::fake()->create('questions.csv', 12, 'text/csv'))
            ->set('data.dry_run', true)
            ->call('create')
            ->assertHasNoErrors();

        Queue::assertPushed(ProcessQcmImport::class);

        $import = QcmImport::sole();
        $this->assertSame('questions.csv', $import->filename);
        $this->assertTrue($import->dry_run);
        $this->assertSame(ImportStatus::Pending, $import->status);
        $this->assertSame($admin->id, $import->user_id);
        $this->assertNotEmpty($import->file_path);
        Storage::disk('local')->assertExists($import->file_path);
    }
}
