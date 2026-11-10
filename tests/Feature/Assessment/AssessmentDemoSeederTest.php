<?php

namespace Tests\Feature\Assessment;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Assessment\Database\Seeders\AssessmentDemoSeeder;
use Modules\Assessment\Models\ClinicalCase;
use Modules\Assessment\Models\FlashcardDeck;
use Modules\Assessment\Models\Question;
use Modules\Library\Database\Seeders\LibraryDemoSeeder;
use Tests\TestCase;

class AssessmentDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeders_create_postman_ready_content(): void
    {
        $this->seed(LibraryDemoSeeder::class);
        $this->seed(AssessmentDemoSeeder::class);

        $this->assertDatabaseCount('subjects', 8);
        $this->assertDatabaseCount('levels', 7);
        $this->assertDatabaseCount('assessment_questions', 9);
        $this->assertDatabaseCount('assessment_question_options', 36);
        $this->assertDatabaseCount('assessment_flashcard_decks', 3);
        $this->assertDatabaseCount('assessment_flashcards', 9);
        $this->assertDatabaseCount('assessment_clinical_cases', 3);
        $this->assertDatabaseCount('assessment_clinical_case_steps', 9);

        $this->assertSame(8, Question::query()->where('status', 'published')->count());
        $this->assertSame(1, Question::query()->where('status', 'draft')->count());
        $this->assertSame(2, FlashcardDeck::query()->where('status', 'published')->count());
        $this->assertSame(1, FlashcardDeck::query()->where('status', 'draft')->count());
        $this->assertSame(2, ClinicalCase::query()->where('status', 'published')->count());
        $this->assertSame(1, ClinicalCase::query()->where('status', 'draft')->count());

        Question::query()->each(function (Question $question): void {
            $question->load('options');

            $this->assertCount(4, $question->options);
            $this->assertCount(1, $question->options->where('is_correct', true));
        });
    }

    public function test_demo_seeders_are_idempotent(): void
    {
        $this->seed(LibraryDemoSeeder::class);
        $this->seed(AssessmentDemoSeeder::class);

        // Rejouer les deux seeders ne doit créer aucun doublon.
        $this->seed(LibraryDemoSeeder::class);
        $this->seed(AssessmentDemoSeeder::class);

        $this->assertDatabaseCount('subjects', 8);
        $this->assertDatabaseCount('levels', 7);
        $this->assertDatabaseCount('assessment_questions', 9);
        $this->assertDatabaseCount('assessment_question_options', 36);
        $this->assertDatabaseCount('assessment_flashcard_decks', 3);
        $this->assertDatabaseCount('assessment_flashcards', 9);
        $this->assertDatabaseCount('assessment_clinical_cases', 3);
        $this->assertDatabaseCount('assessment_clinical_case_steps', 9);
    }
}
