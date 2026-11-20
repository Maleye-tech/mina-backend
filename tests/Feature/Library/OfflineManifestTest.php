<?php

namespace Tests\Feature\Library;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Modules\Library\Models\Content;
use Modules\Library\Models\Level;
use Modules\Library\Models\Podcast;
use Modules\Library\Models\Subject;
use Tests\TestCase;

class OfflineManifestTest extends TestCase
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

    public function test_manifest_requires_authentication(): void
    {
        $this->getJson('/api/v1/library/offline-manifest')->assertUnauthorized();
    }

    public function test_manifest_contains_taxonomies_and_all_published_contents(): void
    {
        Subject::factory()->create(['name' => 'Anatomie', 'sort_order' => -1]);
        Level::factory()->create(['name' => 'PCE1', 'sort_order' => -1]);
        Content::factory()->create(['title' => 'Publié']);
        Content::factory()->unpublished()->create(['title' => 'Brouillon']);

        $this->withToken($this->token)
            ->getJson('/api/v1/library/offline-manifest')
            ->assertOk()
            ->assertJsonPath('subjects.0.name', 'Anatomie')
            ->assertJsonPath('levels.0.name', 'PCE1')
            ->assertJsonCount(1, 'contents')
            ->assertJsonPath('contents.0.title', 'Publié')
            ->assertJsonStructure(['generated_at', 'signed_url_ttl_minutes']);
    }

    public function test_manifest_contains_only_favorited_published_podcasts_with_signed_urls(): void
    {
        $favorited = Podcast::factory()->create(['title' => 'À écouter']);
        Podcast::factory()->create(['title' => 'Non favori']);
        Podcast::factory()->unpublished()->create(['title' => 'Brouillon favori']);

        $this->withToken($this->token)
            ->postJson("/api/v1/library/podcasts/{$favorited->id}/favorite")
            ->assertOk();

        $brouillon = Podcast::query()->where('title', 'Brouillon favori')->firstOrFail();
        $this->user->favorites()->create([
            'favoritable_type' => Podcast::class,
            'favoritable_id' => $brouillon->id,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/library/offline-manifest')
            ->assertOk()
            ->assertJsonCount(1, 'podcasts')
            ->assertJsonPath('podcasts.0.title', 'À écouter')
            ->assertJsonStructure(['podcasts' => [['id', 'title', 'audio_url']]]);

        $this->assertNotEmpty($response->json('podcasts.0.audio_url'));
        $this->assertCount(2, $response->json('favorites.podcasts'));
    }

    public function test_manifest_lists_favorite_ids(): void
    {
        $content = Content::factory()->create();

        $this->withToken($this->token)
            ->postJson("/api/v1/library/contents/{$content->id}/favorite")
            ->assertOk();

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/library/offline-manifest')
            ->assertOk();

        $this->assertSame(
            [$content->id],
            array_column($response->json('favorites.contents'), 'id'),
        );
        $this->assertSame([], $response->json('favorites.podcasts'));
    }
}
