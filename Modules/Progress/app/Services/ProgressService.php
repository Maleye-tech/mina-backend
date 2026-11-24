<?php

namespace Modules\Progress\Services;

use App\Models\User;
use App\Models\UserConsent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Assessment\Models\Attempt;
use Modules\Assessment\Models\AttemptAnswer;
use Modules\Library\Models\Subject;

class ProgressService
{
    public function dashboard(User $user): array
    {
        $completed = Attempt::query()->forUser($user)->completed();

        $aggregate = $completed->toBase()->selectRaw(
            'count(*) as sessions_count,
             avg(score) as average_score,
             max(score) as best_score,
             sum(total_questions) as total_questions,
             sum(correct_count) as correct_answers,
             sum(time_spent_seconds) as total_time_spent_seconds'
        )->first();

        $correctAnswers = (int) ($aggregate->correct_answers ?? 0);
        $totalQuestions = (int) ($aggregate->total_questions ?? 0);

        return [
            'sessions_count' => (int) ($aggregate->sessions_count ?? 0),
            'average_score' => $aggregate->average_score !== null ? (float) $aggregate->average_score : null,
            'best_score' => $aggregate->best_score !== null ? (float) $aggregate->best_score : null,
            'total_questions' => $totalQuestions,
            'correct_answers' => $correctAnswers,
            'success_rate' => $totalQuestions > 0 ? round(($correctAnswers / $totalQuestions) * 100, 2) : null,
            'total_time_spent_seconds' => (int) ($aggregate->total_time_spent_seconds ?? 0),
            'recent_history' => Attempt::query()
                ->forUser($user)
                ->completed()
                ->with(['subject:id,name,slug', 'level:id,name,slug'])
                ->latest('completed_at')
                ->limit(5)
                ->get(),
            'weak_points' => $this->weakPoints($user, 5),
            'well_being' => $this->wellBeing($user),
        ];
    }

    public function stats(User $user, ?int $subjectId = null): array
    {
        $query = Attempt::query()->forUser($user)->completed();

        if ($subjectId !== null) {
            $query->where('subject_id', $subjectId);
        }

        $aggregates = $query->toBase()->select(
            'subject_id',
            DB::raw('count(*) as sessions_count'),
            DB::raw('avg(score) as average_score'),
            DB::raw('max(score) as best_score'),
            DB::raw('sum(total_questions) as total_questions'),
            DB::raw('sum(correct_count) as correct_answers'),
        )->groupBy('subject_id')->orderByDesc('sessions_count')->get();

        $subjectIds = $aggregates->pluck('subject_id')->filter()->all();
        $subjects = $subjectIds === [] ? collect() : Subject::query()->whereIn('id', $subjectIds)->get()->keyBy('id');

        return $aggregates->map(function ($row) use ($subjects): array {
            $subject = $subjects[$row->subject_id] ?? null;
            $totalQuestions = (int) ($row->total_questions ?? 0);
            $correctAnswers = (int) ($row->correct_answers ?? 0);

            return [
                'subject_id' => $row->subject_id,
                'subject' => $subject ? [
                    'id' => $subject->id,
                    'name' => $subject->name,
                    'slug' => $subject->slug,
                ] : null,
                'sessions_count' => (int) $row->sessions_count,
                'average_score' => $row->average_score !== null ? (float) $row->average_score : null,
                'best_score' => $row->best_score !== null ? (float) $row->best_score : null,
                'total_questions' => $totalQuestions,
                'correct_answers' => $correctAnswers,
                'success_rate' => $totalQuestions > 0 ? round(($correctAnswers / $totalQuestions) * 100, 2) : null,
            ];
        })->values()->all();
    }

    public function history(User $user, ?int $subjectId = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = Attempt::query()->forUser($user)->completed();

        if ($subjectId !== null) {
            $query->where('subject_id', $subjectId);
        }

        return $query->with(['subject:id,name,slug', 'level:id,name,slug'])
            ->latest('completed_at')
            ->paginate($perPage);
    }

    public function weakPoints(User $user, int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        return AttemptAnswer::query()
            ->select('question_id', DB::raw('count(*) as failure_count'), DB::raw('max(answered_at) as last_failed_at'))
            ->whereHas('attempt', fn (Builder $q) => $q->forUser($user)->completed())
            ->where('is_correct', false)
            ->groupBy('question_id')
            ->orderByDesc('failure_count')
            ->limit($limit)
            ->get()
            ->load(['question', 'question.subject:id,name,slug', 'question.level:id,name,slug']);
    }

    private function wellBeing(User $user): ?array
    {
        $status = $user->consentStatus(UserConsent::MENTAL_HEALTH_DATA);

        return $status['granted'] === true ? $status : null;
    }
}
