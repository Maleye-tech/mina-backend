<?php

namespace Tests\Unit\WellBeing;

use Modules\WellBeing\Enums\AssessmentTestType;
use Modules\WellBeing\Services\TestScoringService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TestScoringServiceTest extends TestCase
{
    public static function percentages(): array
    {
        return [
            'zero max score' => [0, 0, 0],
            'zero points' => [0, 15, 0],
            'one third rounds down' => [1, 3, 33],
            'two thirds rounds up' => [2, 3, 67],
            'half' => [3, 6, 50],
            'full score' => [15, 15, 100],
        ];
    }

    #[DataProvider('percentages')]
    public function test_percentage_is_rounded_to_the_nearest_integer(int $score, int $maxScore, int $expected): void
    {
        $service = new TestScoringService;

        $this->assertSame($expected, $service->percentage($score, $maxScore));
    }

    public static function anxietyRanges(): array
    {
        return [
            'low' => [0, 15, 'low'],
            'moderate' => [6, 15, 'moderate'],
            'high' => [15, 15, 'high'],
        ];
    }

    #[DataProvider('anxietyRanges')]
    public function test_feedback_for_anxiety_uses_the_configured_ranges(int $score, int $maxScore, string $level): void
    {
        $service = new TestScoringService;

        $feedback = $service->feedbackFor(AssessmentTestType::Anxiety, $score, $maxScore);

        $this->assertSame($level, $feedback['level']);
        $this->assertNotEmpty($feedback['message']);
    }

    public function test_feedback_matches_the_configuration_for_each_type(): void
    {
        $service = new TestScoringService;

        foreach (AssessmentTestType::cases() as $type) {
            foreach (config("wellbeing.scoring.{$type->value}") as $range) {
                $maxScore = 100;
                $score = (int) $range['max_percentage'];

                $feedback = $service->feedbackFor($type, $score, $maxScore);

                $this->assertSame($range['level'], $feedback['level']);
                $this->assertSame($range['feedback'], $feedback['message']);
            }
        }
    }

    public function test_wellbeing_high_score_is_positive(): void
    {
        $service = new TestScoringService;

        $feedback = $service->feedbackFor(AssessmentTestType::Wellbeing, 15, 15);

        $this->assertSame('high', $feedback['level']);
        $this->assertSame(config('wellbeing.scoring.wellbeing.2.feedback'), $feedback['message']);
    }

    public function test_the_disclaimer_is_the_required_exact_text(): void
    {
        $this->assertSame("Ce n'est pas un diagnostic médical.", TestScoringService::DISCLAIMER);
        $this->assertSame(TestScoringService::DISCLAIMER, config('wellbeing.disclaimer'));
    }
}
