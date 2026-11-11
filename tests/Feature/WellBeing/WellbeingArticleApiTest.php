<?php

namespace Tests\Feature\WellBeing;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Enums\UserRole;
use Modules\WellBeing\Enums\WellbeingCategory;
use Modules\WellBeing\Models\WellbeingArticle;
use Tests\Feature\WellBeing\Concerns\InteractsWithWellBeing;
use Tests\TestCase;

class WellbeingArticleApiTest extends TestCase
{
    use InteractsWithWellBeing;
    use RefreshDatabase;

    public function test_index_only_returns_published_articles(): void
    {
        $published = WellbeingArticle::factory()->published()->create(['title' => 'Fiche publiée']);
        WellbeingArticle::factory()->draft()->create(['title' => 'Fiche brouillon']);

        $user = $this->userWithConsent();

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/v1/wellbeing/articles')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $published->id)
            ->assertJsonPath('data.0.is_published', true);
    }

    public function test_index_can_be_filtered_by_category(): void
    {
        $stress = WellbeingArticle::factory()
            ->published()
            ->ofCategory(WellbeingCategory::Stress)
            ->create();

        WellbeingArticle::factory()
            ->published()
            ->ofCategory(WellbeingCategory::Sleep)
            ->create();

        $user = $this->userWithConsent();

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/v1/wellbeing/articles?category=stress')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $stress->id)
            ->assertJsonPath('data.0.category', WellbeingCategory::Stress->value);
    }

    public function test_index_rejects_an_unknown_category(): void
    {
        $user = $this->userWithConsent();

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/v1/wellbeing/articles?category=unknown')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category']);
    }

    public function test_index_respects_the_per_page_limit(): void
    {
        WellbeingArticle::factory()->published()->count(3)->create();

        $user = $this->userWithConsent();

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/v1/wellbeing/articles?per_page=101')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);

        $this->app['auth']->forgetGuards();

        $this->withToken($this->tokenFor($user))
            ->getJson('/api/v1/wellbeing/articles?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_a_published_article_can_be_viewed_by_its_slug(): void
    {
        $article = WellbeingArticle::factory()->published()->create([
            'title' => 'Gérer son stress',
            'content' => '# Contenu de la fiche',
        ]);

        $user = $this->userWithConsent();

        $this->withToken($this->tokenFor($user))
            ->getJson("/api/v1/wellbeing/articles/{$article->slug}")
            ->assertOk()
            ->assertJsonPath('data.id', $article->id)
            ->assertJsonPath('data.slug', $article->slug)
            ->assertJsonPath('data.content', '# Contenu de la fiche');
    }

    public function test_a_draft_article_is_not_found_for_a_regular_user(): void
    {
        $article = WellbeingArticle::factory()->draft()->create();

        $user = $this->userWithConsent();

        $this->withToken($this->tokenFor($user))
            ->getJson("/api/v1/wellbeing/articles/{$article->slug}")
            ->assertNotFound();
    }

    public function test_an_admin_can_view_a_draft_article(): void
    {
        $article = WellbeingArticle::factory()->draft()->create();

        $admin = $this->userWithConsent(UserRole::Admin);

        $this->withToken($this->tokenFor($admin))
            ->getJson("/api/v1/wellbeing/articles/{$article->slug}")
            ->assertOk()
            ->assertJsonPath('data.id', $article->id)
            ->assertJsonPath('data.is_published', false);
    }
}
