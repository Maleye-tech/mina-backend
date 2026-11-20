<?php

namespace Tests\Feature\Library;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Modules\Auth\Enums\UserRole;
use Modules\Library\Enums\CompressionStatus;
use Modules\Library\Jobs\CompressPodcastAudio;
use Modules\Library\Models\Level;
use Modules\Library\Models\Podcast;
use Modules\Library\Models\Subject;
use Tests\TestCase;

class LibraryPodcastsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Storage::fake(config('library.audio_disk'));

        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('library-session', ['full-access'])->plainTextToken;
    }

    public function test_listing_requires_authentication(): void
    {
        $this->getJson('/api/v1/library/podcasts')->assertUnauthorized();
    }

    public function test_list_returns_only_published_podcasts_with_audio_url(): void
    {
        Podcast::factory()->create(['title' => 'Podcast publié', 'duration_seconds' => 125]);
        Podcast::factory()->unpublished()->create(['title' => 'Brouillon']);

        $this->withToken($this->token)
            ->getJson('/api/v1/library/podcasts')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Podcast publié')
            ->assertJsonPath('data.0.duration_seconds', 125)
            ->assertJsonPath('data.0.compression_status', CompressionStatus::Pending->value)
            ->assertJsonStructure(['data' => [['audio_url']]]);
    }

    public function test_show_returns_404_for_unpublished_podcast(): void
    {
        $podcast = Podcast::factory()->unpublished()->create();

        $this->withToken($this->token)
            ->getJson("/api/v1/library/podcasts/{$podcast->id}")
            ->assertNotFound();
    }

    public function test_user_can_favorite_a_podcast(): void
    {
        $podcast = Podcast::factory()->create();

        $this->withToken($this->token)
            ->postJson("/api/v1/library/podcasts/{$podcast->id}/favorite")
            ->assertOk()
            ->assertJsonPath('favorited', true);

        $this->withToken($this->token)
            ->getJson('/api/v1/library/podcasts?favorite=1')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withToken($this->token)
            ->deleteJson("/api/v1/library/podcasts/{$podcast->id}/favorite")
            ->assertOk()
            ->assertJsonPath('favorited', false);
    }

    public function test_admin_can_upload_a_podcast_and_the_compression_is_queued(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $token = $admin->createToken('admin', ['full-access'])->plainTextToken;
        $subject = Subject::factory()->create();
        $level = Level::factory()->create();

        $response = $this->withToken($token)->post('/api/v1/library/podcasts', [
            'audio' => UploadedFile::fake()->create('cours.mp3', 500, 'audio/mpeg'),
            'title' => 'Podcast cardiaque',
            'description' => 'Les bases de la circulation',
            'subject_id' => $subject->id,
            'level_id' => $level->id,
            'is_published' => true,
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('podcast.title', 'Podcast cardiaque')
            ->assertJsonPath('podcast.is_published', true);

        $podcast = Podcast::query()->firstOrFail();
        $this->assertSame($admin->id, $podcast->uploaded_by);
        Storage::disk(config('library.audio_disk'))->assertExists($podcast->original_path);
        Queue::assertPushed(CompressPodcastAudio::class, fn ($job) => $job->podcastId === $podcast->id);
    }

    public function test_non_admin_cannot_upload_a_podcast(): void
    {
        $subject = Subject::factory()->create();
        $level = Level::factory()->create();

        $this->withToken($this->token)
            ->post('/api/v1/library/podcasts', [
                'audio' => UploadedFile::fake()->create('cours.mp3', 10, 'audio/mpeg'),
                'title' => 'Tentative',
                'subject_id' => $subject->id,
                'level_id' => $level->id,
            ], ['Accept' => 'application/json'])
            ->assertForbidden();

        $this->assertDatabaseCount('library_podcasts', 0);
    }

    public function test_upload_rejects_unsupported_file_types(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $token = $admin->createToken('admin', ['full-access'])->plainTextToken;
        $subject = Subject::factory()->create();
        $level = Level::factory()->create();

        $this->withToken($token)
            ->post('/api/v1/library/podcasts', [
                'audio' => UploadedFile::fake()->create('document.pdf', 10, 'application/pdf'),
                'title' => 'Mauvais fichier',
                'subject_id' => $subject->id,
                'level_id' => $level->id,
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['audio']);

        $this->assertDatabaseCount('library_podcasts', 0);
    }
}
