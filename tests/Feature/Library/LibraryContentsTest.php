<?php

namespace Tests\Feature\Library;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Library\Enums\ContentType;
use Modules\Library\Models\Content;
use Modules\Library\Models\Level;
use Modules\Library\Models\Subject;
use Tests\TestCase;

class LibraryContentsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('library-session', ['full-access'])->plainTextToken;
    }

    public function test_listing_requires_authentication(): void
    {
        $this->getJson('/api/v1/library/contents')->assertUnauthorized();
    }

    public function test_list_returns_only_published_contents_sorted(): void
    {
        Content::factory()->create(['title' => 'Publié A', 'sort_order' => 2]);
        Content::factory()->create(['title' => 'Publié B', 'sort_order' => 1]);
        Content::factory()->unpublished()->create(['title' => 'Brouillon']);

        $this->withToken($this->token)
            ->getJson('/api/v1/library/contents')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'Publié B')
            ->assertJsonPath('data.1.title', 'Publié A')
            ->assertJsonMissingPath('data.0.body');
    }

    public function test_list_can_filter_by_search_subject_level_and_type(): void
    {
        $subject = Subject::factory()->create();
        $level = Level::factory()->create();

        Content::factory()->create([
            'title' => 'Cœur et circulation',
            'subject_id' => $subject->id,
            'level_id' => $level->id,
            'type' => ContentType::Course,
        ]);
        Content::factory()->create([
            'title' => 'Autre sujet',
            'type' => ContentType::Recap,
        ]);

        $this->withToken($this->token)
            ->getJson('/api/v1/library/contents?search=circulation')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Cœur et circulation');

        $this->withToken($this->token)
            ->getJson("/api/v1/library/contents?subject_id={$subject->id}&level_id={$level->id}&type=course")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withToken($this->token)
            ->getJson('/api/v1/library/contents?type=recap')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'recap')
            ->assertJsonPath('data.0.type_label', 'Résumé');
    }

    public function test_show_returns_content_body(): void
    {
        $content = Content::factory()->create(['body' => '<p>Le cœur pompe le sang.</p>']);

        $this->withToken($this->token)
            ->getJson("/api/v1/library/contents/{$content->id}")
            ->assertOk()
            ->assertJsonPath('content.body', '<p>Le cœur pompe le sang.</p>')
            ->assertJsonPath('content.subject.id', $content->subject_id);
    }

    public function test_show_returns_404_for_unpublished_content(): void
    {
        $content = Content::factory()->unpublished()->create();

        $this->withToken($this->token)
            ->getJson("/api/v1/library/contents/{$content->id}")
            ->assertNotFound();
    }

    public function test_user_can_favorite_and_unfavorite_a_content(): void
    {
        $content = Content::factory()->create();

        $this->withToken($this->token)
            ->postJson("/api/v1/library/contents/{$content->id}/favorite")
            ->assertOk()
            ->assertJsonPath('favorited', true);

        $this->assertDatabaseCount('library_favorites', 1);

        $this->withToken($this->token)
            ->postJson("/api/v1/library/contents/{$content->id}/favorite")
            ->assertOk();

        $this->assertDatabaseCount('library_favorites', 1);

        $this->withToken($this->token)
            ->deleteJson("/api/v1/library/contents/{$content->id}/favorite")
            ->assertOk()
            ->assertJsonPath('favorited', false);

        $this->assertDatabaseCount('library_favorites', 0);
    }

    public function test_list_can_filter_favorites_per_user(): void
    {
        $favorited = Content::factory()->create(['title' => 'Favori']);
        Content::factory()->create(['title' => 'Normal']);

        $this->withToken($this->token)
            ->postJson("/api/v1/library/contents/{$favorited->id}/favorite")
            ->assertOk();

        $this->withToken($this->token)
            ->getJson('/api/v1/library/contents?favorite=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Favori')
            ->assertJsonPath('data.0.favorited', true);

        $otherUser = User::factory()->create();
        $otherToken = $otherUser->createToken('other', ['full-access'])->plainTextToken;
        $this->app['auth']->forgetGuards();

        $this->withToken($otherToken)
            ->getJson('/api/v1/library/contents?favorite=1')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
