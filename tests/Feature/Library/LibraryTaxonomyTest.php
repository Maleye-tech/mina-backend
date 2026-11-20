<?php

namespace Tests\Feature\Library;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Library\Models\Level;
use Modules\Library\Models\Subject;
use Tests\TestCase;

class LibraryTaxonomyTest extends TestCase
{
    use RefreshDatabase;

    public function test_listing_requires_authentication(): void
    {
        $this->getJson('/api/v1/library/subjects')->assertUnauthorized();
        $this->getJson('/api/v1/library/levels')->assertUnauthorized();
    }

    public function test_user_can_list_subjects(): void
    {
        Subject::factory()->create(['name' => 'Anatomie', 'sort_order' => 1]);
        Subject::factory()->create(['name' => 'Biochimie', 'sort_order' => 2]);
        $token = User::factory()->create()->createToken('library-session', ['full-access']);

        $this->withToken($token->plainTextToken)
            ->getJson('/api/v1/library/subjects')
            ->assertOk()
            ->assertJsonPath('subjects.0.name', 'Anatomie')
            ->assertJsonPath('subjects.1.name', 'Biochimie');
    }

    public function test_user_can_list_levels(): void
    {
        Level::factory()->create(['name' => 'PCE1', 'sort_order' => 1]);
        $token = User::factory()->create()->createToken('library-session', ['full-access']);

        $this->withToken($token->plainTextToken)
            ->getJson('/api/v1/library/levels')
            ->assertOk()
            ->assertJsonPath('levels.0.name', 'PCE1')
            ->assertJsonStructure([
                'levels' => [['id', 'name', 'slug', 'sort_order']],
            ]);
    }
}
