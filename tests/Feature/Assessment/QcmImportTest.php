<?php

namespace Tests\Feature\Assessment;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Modules\Assessment\Enums\ImportStatus;
use Modules\Assessment\Jobs\ProcessQcmImport;
use Modules\Assessment\Models\QcmImport;
use Modules\Assessment\Models\Question;
use Modules\Assessment\Services\QcmImportService;
use Modules\Auth\Enums\UserRole;
use Modules\Library\Database\Seeders\LibraryDemoSeeder;
use Modules\Library\Models\Level;
use Modules\Library\Models\Subject;
use Tests\TestCase;

class QcmImportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Authentifier un utilisateur via Sanctum avec l'accès complet.
     */
    private function actingAsUser(UserRole $role = UserRole::Student): User
    {
        $this->app['auth']->forgetGuards();

        $user = User::factory()->create(['role' => $role]);
        $token = $user->createToken('qcm-import-test', ['full-access']);

        $this->withToken($token->plainTextToken);

        return $user;
    }

    private function csvHeader(): string
    {
        return 'subject,level,question,option_a,option_b,option_c,option_d,option_e,correct,difficulty,question_type,status,explanation,source_reference';
    }

    private function sampleCsv(): string
    {
        return (string) file_get_contents(base_path('tests/Fixtures/qcm-import.csv'));
    }

    private function storeImport(string $csv, bool $dryRun = false): QcmImport
    {
        Storage::disk('local')->put('assessment-imports/test.csv', $csv);

        return QcmImport::factory()->create([
            'file_path' => 'assessment-imports/test.csv',
            'dry_run' => $dryRun,
            'status' => ImportStatus::Pending,
        ]);
    }

    public function test_admin_can_upload_a_csv_and_the_import_is_queued(): void
    {
        Storage::fake('local');
        Queue::fake();

        $this->actingAsUser(UserRole::Admin);

        $file = UploadedFile::fake()->createWithContent(
            'questions.csv',
            $this->csvHeader()."\nsante-mentale,troisieme-annee,Question de test ?,Option A,Option B,,,,A,easy,single,published,,",
        );

        $response = $this->post('/api/v1/qcm/imports', [
            'file' => $file,
            'dry_run' => true,
        ], ['Accept' => 'application/json']);

        $response
            ->assertStatus(202)
            ->assertJsonPath('message', 'Import de QCM lancé.')
            ->assertJsonPath('import.status', 'pending')
            ->assertJsonPath('import.dry_run', true)
            ->assertJsonPath('import.filename', 'questions.csv');

        $import = QcmImport::query()->firstOrFail();

        $this->assertSame('questions.csv', $import->filename);
        $this->assertTrue($import->dry_run);
        $this->assertTrue(Storage::disk('local')->exists($import->file_path));

        Queue::assertPushed(
            ProcessQcmImport::class,
            fn (ProcessQcmImport $job): bool => $job->import->is($import),
        );
    }

    public function test_non_admin_cannot_access_imports(): void
    {
        Storage::fake('local');

        $this->actingAsUser(UserRole::Student);

        $this->getJson('/api/v1/qcm/imports')->assertForbidden();

        $this->post('/api/v1/qcm/imports', [
            'file' => UploadedFile::fake()->createWithContent('questions.csv', $this->csvHeader()),
        ], ['Accept' => 'application/json'])->assertForbidden();
    }

    public function test_upload_requires_a_csv_file(): void
    {
        Storage::fake('local');

        $this->actingAsUser(UserRole::Admin);

        $this->postJson('/api/v1/qcm/imports', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);

        $this->post('/api/v1/qcm/imports', [
            'file' => UploadedFile::fake()->create('document.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    }

    public function test_import_processes_the_sample_fixture(): void
    {
        Storage::fake('local');
        $this->seed(LibraryDemoSeeder::class);

        $processed = app(QcmImportService::class)->process($this->storeImport($this->sampleCsv()));

        $this->assertSame(ImportStatus::Completed, $processed->status);
        $this->assertSame(3, $processed->total_rows);
        $this->assertSame(3, $processed->imported_count);
        $this->assertSame(0, $processed->duplicate_count);
        $this->assertSame(0, $processed->failed_count);
        $this->assertNull($processed->errors);

        $this->assertSame(3, Question::query()->count());
        $this->assertSame(12, Question::query()->withCount('options')->get()->sum('options_count'));
        $this->assertSame(2, Question::query()->where('status', 'published')->count());
        $this->assertSame(1, Question::query()->where('status', 'draft')->count());

        $question = Question::query()
            ->where('question', 'like', 'Quel neurotransmetteur%')
            ->with('options')
            ->firstOrFail();

        $this->assertSame('La sérotonine', $question->options->firstWhere('is_correct', true)?->label);
    }

    public function test_import_skips_duplicates_and_reports_invalid_rows(): void
    {
        Storage::fake('local');

        Subject::factory()->create(['slug' => 'sante-mentale', 'name' => 'Santé mentale']);
        Level::factory()->create(['slug' => 'troisieme-annee', 'name' => '3e année']);

        $csv = implode("\n", [
            $this->csvHeader(),
            'sante-mentale,troisieme-annee,Question valide ?,Option A,Option B,,,,A,easy,single,published,,',
            'sante-mentale,troisieme-annee,  question   valide ?,Option A bis,Option B bis,,,,B,easy,single,published,,',
            'matiere-inconnue,troisieme-annee,Question invalide ?,Option A,Option B,,,,A,easy,single,published,,',
        ]);

        $processed = app(QcmImportService::class)->process($this->storeImport($csv));

        $this->assertSame(ImportStatus::Completed, $processed->status);
        $this->assertSame(3, $processed->total_rows);
        $this->assertSame(1, $processed->imported_count);
        $this->assertSame(1, $processed->duplicate_count);
        $this->assertSame(1, $processed->failed_count);
        $this->assertNotNull($processed->errors);
        $this->assertSame(4, $processed->errors[0]['row']);
        $this->assertStringContainsString('matière', $processed->errors[0]['errors'][0]);
        $this->assertSame(1, Question::query()->count());
    }

    public function test_duplicate_of_a_soft_deleted_question_is_skipped(): void
    {
        Storage::fake('local');
        $this->seed(LibraryDemoSeeder::class);

        $csv = $this->csvHeader()."\nsante-mentale,troisieme-annee,Question supprimée ?,Option A,Option B,,,,A,easy,single,published,,";

        app(QcmImportService::class)->process($this->storeImport($csv));

        Question::query()->firstOrFail()->delete();

        $processed = app(QcmImportService::class)->process($this->storeImport($csv));

        $this->assertSame(0, $processed->imported_count);
        $this->assertSame(1, $processed->duplicate_count);
        $this->assertSame(1, Question::withTrashed()->count());
    }

    public function test_dry_run_validates_without_persisting_questions(): void
    {
        Storage::fake('local');
        $this->seed(LibraryDemoSeeder::class);

        $processed = app(QcmImportService::class)->process($this->storeImport($this->sampleCsv(), dryRun: true));

        $this->assertSame(ImportStatus::Completed, $processed->status);
        $this->assertSame(3, $processed->imported_count);
        $this->assertSame(0, $processed->failed_count);
        $this->assertSame(0, Question::query()->count());
        $this->assertDatabaseCount('assessment_questions', 0);
    }

    public function test_missing_file_marks_the_import_as_failed(): void
    {
        Storage::fake('local');

        $import = QcmImport::factory()->create([
            'file_path' => 'assessment-imports/missing.csv',
        ]);

        $processed = app(QcmImportService::class)->process($import);

        $this->assertSame(ImportStatus::Failed, $processed->status);
        $this->assertNotNull($processed->errors);
        $this->assertSame(0, $processed->total_rows);
    }

    public function test_job_processes_the_import(): void
    {
        Storage::fake('local');
        $this->seed(LibraryDemoSeeder::class);

        $import = $this->storeImport($this->sampleCsv());

        (new ProcessQcmImport($import))->handle(app(QcmImportService::class));

        $this->assertSame(ImportStatus::Completed, $import->fresh()->status);
        $this->assertSame(3, Question::query()->count());
    }

    public function test_admin_can_list_and_show_imports(): void
    {
        $this->actingAsUser(UserRole::Admin);

        $imports = QcmImport::factory()->count(2)->create();

        $this->getJson('/api/v1/qcm/imports')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson("/api/v1/qcm/imports/{$imports->first()->id}")
            ->assertOk()
            ->assertJsonPath('import.id', $imports->first()->id);
    }

    public function test_import_history_can_be_filtered_by_status(): void
    {
        $this->actingAsUser(UserRole::Admin);

        QcmImport::factory()->create(['status' => ImportStatus::Completed]);
        QcmImport::factory()->create(['status' => ImportStatus::Failed]);

        $this->getJson('/api/v1/qcm/imports?status=completed')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'completed');
    }
}
