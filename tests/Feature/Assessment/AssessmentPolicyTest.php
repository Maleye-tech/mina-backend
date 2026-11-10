<?php

namespace Tests\Feature\Assessment;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Modules\Assessment\Models\Attempt;
use Modules\Assessment\Models\Flashcard;
use Modules\Assessment\Models\FlashcardDeck;
use Modules\Auth\Enums\UserRole;
use Tests\TestCase;

class AssessmentPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_attempt_policies_allow_the_owner_and_administrators(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Student]);
        $intruder = User::factory()->create(['role' => UserRole::Student]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $attempt = Attempt::factory()->create(['user_id' => $owner->id]);

        foreach (['view', 'answer', 'finish'] as $ability) {
            $this->assertTrue(Gate::forUser($owner)->allows($ability, $attempt));
            $this->assertTrue(Gate::forUser($admin)->allows($ability, $attempt));
            $this->assertFalse(Gate::forUser($intruder)->allows($ability, $attempt));
        }
    }

    public function test_flashcard_policies_reserve_writes_to_administrators(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $student = User::factory()->create(['role' => UserRole::Student]);

        $deck = FlashcardDeck::factory()->create();
        $flashcard = Flashcard::factory()->create(['deck_id' => $deck->id]);

        $this->assertTrue(Gate::forUser($admin)->allows('create', FlashcardDeck::class));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $deck));
        $this->assertTrue(Gate::forUser($admin)->allows('delete', $deck));

        $this->assertTrue(Gate::forUser($admin)->allows('create', Flashcard::class));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $flashcard));
        $this->assertTrue(Gate::forUser($admin)->allows('delete', $flashcard));

        $this->assertFalse(Gate::forUser($student)->allows('create', FlashcardDeck::class));
        $this->assertFalse(Gate::forUser($student)->allows('update', $deck));
        $this->assertFalse(Gate::forUser($student)->allows('delete', $deck));

        $this->assertFalse(Gate::forUser($student)->allows('create', Flashcard::class));
        $this->assertFalse(Gate::forUser($student)->allows('update', $flashcard));
        $this->assertFalse(Gate::forUser($student)->allows('delete', $flashcard));
    }
}
