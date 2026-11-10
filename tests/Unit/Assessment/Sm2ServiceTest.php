<?php

namespace Tests\Unit\Assessment;

use Carbon\CarbonImmutable;
use Modules\Assessment\Enums\ReviewQuality;
use Modules\Assessment\Models\FlashcardState;
use Modules\Assessment\Services\Sm2Service;
use PHPUnit\Framework\TestCase;

class Sm2ServiceTest extends TestCase
{
    private Sm2Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new Sm2Service;
    }

    public function test_first_successful_review_schedules_one_day(): void
    {
        $attributes = $this->service->calculate($this->makeState(), ReviewQuality::Good);

        $this->assertSame(1, $attributes['interval_days']);
        $this->assertSame(1, $attributes['repetitions']);
        $this->assertSame(0, $attributes['lapses']);
        $this->assertSame(4, $attributes['last_quality']);
    }

    public function test_second_successful_review_schedules_six_days(): void
    {
        $attributes = $this->service->calculate(
            $this->makeState(intervalDays: 1, repetitions: 1),
            ReviewQuality::Good,
        );

        $this->assertSame(6, $attributes['interval_days']);
        $this->assertSame(2, $attributes['repetitions']);
    }

    public function test_subsequent_reviews_multiply_the_interval_by_the_easiness_factor(): void
    {
        $attributes = $this->service->calculate(
            $this->makeState(easinessFactor: 2.50, intervalDays: 6, repetitions: 2),
            ReviewQuality::Perfect,
        );

        $this->assertSame(2.6, $attributes['easiness_factor']);
        $this->assertSame(16, $attributes['interval_days']); // round(6 × 2,60) = 16
        $this->assertSame(3, $attributes['repetitions']);
    }

    public function test_a_failed_review_resets_the_streak_and_counts_a_lapse(): void
    {
        $attributes = $this->service->calculate(
            $this->makeState(easinessFactor: 2.60, intervalDays: 16, repetitions: 3, lapses: 1),
            ReviewQuality::Wrong,
        );

        $this->assertSame(0, $attributes['repetitions']);
        $this->assertSame(1, $attributes['interval_days']);
        $this->assertSame(2, $attributes['lapses']);
        $this->assertSame(2.06, $attributes['easiness_factor']);
    }

    public function test_the_easiness_factor_never_drops_below_the_floor(): void
    {
        $attributes = $this->service->calculate(
            $this->makeState(easinessFactor: 1.40, intervalDays: 6, repetitions: 2),
            ReviewQuality::Blackout,
        );

        $this->assertSame(1.3, $attributes['easiness_factor']);
    }

    public function test_a_perfect_review_increases_the_easiness_factor(): void
    {
        $attributes = $this->service->calculate($this->makeState(), ReviewQuality::Perfect);

        $this->assertSame(2.6, $attributes['easiness_factor']);
    }

    public function test_the_minimum_passing_quality_counts_as_a_success(): void
    {
        $attributes = $this->service->calculate(
            $this->makeState(intervalDays: 6, repetitions: 2),
            ReviewQuality::Hard,
        );

        $this->assertSame(3, $attributes['repetitions']);
        $this->assertSame(3, $attributes['last_quality']);
    }

    public function test_a_familiar_answer_counts_as_a_failure(): void
    {
        $attributes = $this->service->calculate(
            $this->makeState(intervalDays: 6, repetitions: 2),
            ReviewQuality::Familiar,
        );

        $this->assertSame(0, $attributes['repetitions']);
        $this->assertSame(1, $attributes['lapses']);
        $this->assertSame(1, $attributes['interval_days']);
    }

    public function test_the_due_date_is_the_review_date_plus_the_interval(): void
    {
        $reviewedAt = CarbonImmutable::create(2026, 11, 10, 9, 30);

        $attributes = $this->service->calculate(
            $this->makeState(intervalDays: 1, repetitions: 1),
            ReviewQuality::Good,
            $reviewedAt,
        );

        $this->assertSame('2026-11-16 09:30', $attributes['due_at']->format('Y-m-d H:i'));
        $this->assertSame('2026-11-10 09:30', $attributes['last_reviewed_at']->format('Y-m-d H:i'));
    }

    /**
     * Construire un état SM-2 en mémoire, sans base de données.
     */
    private function makeState(
        float $easinessFactor = 2.5,
        int $intervalDays = 0,
        int $repetitions = 0,
        int $lapses = 0,
    ): FlashcardState {
        return (new FlashcardState)->forceFill([
            'easiness_factor' => $easinessFactor,
            'interval_days' => $intervalDays,
            'repetitions' => $repetitions,
            'lapses' => $lapses,
        ]);
    }
}
