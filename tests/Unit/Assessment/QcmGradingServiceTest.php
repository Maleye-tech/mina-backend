<?php

namespace Tests\Unit\Assessment;

use Modules\Assessment\Models\Question;
use Modules\Assessment\Models\QuestionOption;
use Modules\Assessment\Services\QcmGradingService;
use PHPUnit\Framework\TestCase;

class QcmGradingServiceTest extends TestCase
{
    private QcmGradingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new QcmGradingService;
    }

    public function test_single_choice_answer_is_correct_when_it_matches_the_correct_option(): void
    {
        $question = $this->makeQuestion(correctOptionIds: [1], optionIds: [1, 2, 3]);

        $this->assertTrue($this->service->gradeAnswer($question, [1]));
    }

    public function test_single_choice_answer_is_incorrect_when_it_does_not_match(): void
    {
        $question = $this->makeQuestion(correctOptionIds: [1], optionIds: [1, 2, 3]);

        $this->assertFalse($this->service->gradeAnswer($question, [2]));
    }

    public function test_multiple_choice_answer_is_correct_regardless_of_selection_order(): void
    {
        $question = $this->makeQuestion(correctOptionIds: [1, 3], optionIds: [1, 2, 3, 4]);

        $this->assertTrue($this->service->gradeAnswer($question, [3, 1]));
    }

    public function test_multiple_choice_answer_is_incorrect_when_an_option_is_missing(): void
    {
        $question = $this->makeQuestion(correctOptionIds: [1, 3], optionIds: [1, 2, 3, 4]);

        $this->assertFalse($this->service->gradeAnswer($question, [1]));
    }

    public function test_multiple_choice_answer_is_incorrect_when_an_extra_option_is_selected(): void
    {
        $question = $this->makeQuestion(correctOptionIds: [1, 3], optionIds: [1, 2, 3, 4]);

        $this->assertFalse($this->service->gradeAnswer($question, [1, 3, 4]));
    }

    public function test_multiple_choice_answer_is_incorrect_when_no_option_is_correct(): void
    {
        $question = $this->makeQuestion(correctOptionIds: [1, 3], optionIds: [1, 2, 3, 4]);

        $this->assertFalse($this->service->gradeAnswer($question, [2, 4]));
    }

    public function test_score_is_the_percentage_of_correct_answers(): void
    {
        $this->assertSame(100.0, $this->service->calculateScore(10, 10));
        $this->assertSame(75.0, $this->service->calculateScore(3, 4));
        $this->assertSame(50.0, $this->service->calculateScore(1, 2));
        $this->assertSame(0.0, $this->service->calculateScore(0, 5));
    }

    public function test_score_is_rounded_to_two_decimals(): void
    {
        $this->assertSame(33.33, $this->service->calculateScore(1, 3));
        $this->assertSame(66.67, $this->service->calculateScore(2, 3));
    }

    public function test_score_is_zero_when_there_is_no_question(): void
    {
        $this->assertSame(0.0, $this->service->calculateScore(0, 0));
    }

    /**
     * Construire une question en mémoire avec ses options, sans base de données.
     *
     * @param  array<int, int>  $correctOptionIds
     * @param  array<int, int>  $optionIds
     */
    private function makeQuestion(array $correctOptionIds, array $optionIds): Question
    {
        $options = collect($optionIds)->map(
            fn (int $id): QuestionOption => (new QuestionOption)->forceFill([
                'id' => $id,
                'is_correct' => in_array($id, $correctOptionIds, true),
            ]),
        );

        return (new Question)->setRelation('options', $options);
    }
}
