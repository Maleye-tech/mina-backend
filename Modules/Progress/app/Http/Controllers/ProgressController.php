<?php

namespace Modules\Progress\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Progress\Http\Requests\ProgressHistoryRequest;
use Modules\Progress\Http\Requests\ProgressStatsRequest;
use Modules\Progress\Http\Requests\ProgressWeakPointsRequest;
use Modules\Progress\Http\Resources\AttemptHistoryResource;
use Modules\Progress\Http\Resources\WeakPointResource;
use Modules\Progress\Services\ProgressService;

class ProgressController extends Controller
{
    public function __construct(private readonly ProgressService $progress) {}

    public function index(Request $request): JsonResponse
    {
        $data = $this->progress->dashboard($request->user());

        return response()->json([
            'sessions_count' => $data['sessions_count'],
            'average_score' => $data['average_score'],
            'best_score' => $data['best_score'],
            'total_questions' => $data['total_questions'],
            'correct_answers' => $data['correct_answers'],
            'success_rate' => $data['success_rate'],
            'total_time_spent_seconds' => $data['total_time_spent_seconds'],
            'recent_history' => AttemptHistoryResource::collection($data['recent_history']),
            'weak_points' => WeakPointResource::collection($data['weak_points']),
            'well_being' => $data['well_being'],
        ]);
    }

    public function stats(ProgressStatsRequest $request): JsonResponse
    {
        return response()->json($this->progress->stats(
            $request->user(),
            $request->integer('subject_id')
        ));
    }

    public function history(ProgressHistoryRequest $request): \Illuminate\Http\Resources\Json\AnonymousResourceCollection
    {
        return AttemptHistoryResource::collection($this->progress->history(
            $request->user(),
            $request->integer('subject_id'),
            (int) $request->integer('per_page', 15),
        ));
    }

    public function weakPoints(ProgressWeakPointsRequest $request): JsonResponse
    {
        return response()->json(WeakPointResource::collection($this->progress->weakPoints(
            $request->user(),
            (int) $request->integer('limit', 10),
        )));
    }
}
