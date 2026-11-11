<?php

namespace Modules\WellBeing\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\WellBeing\Http\Requests\IndexTestsRequest;
use Modules\WellBeing\Http\Requests\SubmitTestRequest;
use Modules\WellBeing\Http\Resources\AssessmentTestResource;
use Modules\WellBeing\Http\Resources\UserTestResultResource;
use Modules\WellBeing\Models\AssessmentTest;
use Modules\WellBeing\Services\TestScoringService;

class AssessmentTestController extends Controller
{
    private const DEFAULT_PER_PAGE = 15;

    private const MAX_PER_PAGE = 100;

    /**
     * Catalogue paginé des tests d'auto-évaluation.
     */
    public function index(IndexTestsRequest $request): AnonymousResourceCollection
    {
        $tests = AssessmentTest::query()
            ->when(
                $request->validated('type'),
                fn ($query, string $type) => $query->where('type', $type),
            )
            ->withCount('questions')
            ->orderBy('id')
            ->paginate(min($request->integer('per_page', self::DEFAULT_PER_PAGE), self::MAX_PER_PAGE));

        return AssessmentTestResource::collection($tests);
    }

    /**
     * Détail d'un test et de ses questions, sans exposer les points.
     */
    public function show(AssessmentTest $test): JsonResponse
    {
        $test->load('questions');

        return response()->json([
            'test' => AssessmentTestResource::make($test),
        ]);
    }

    /**
     * Soumettre les réponses, calculer le score et renvoyer le retour immédiat.
     */
    public function submit(
        SubmitTestRequest $request,
        AssessmentTest $test,
        TestScoringService $scoring,
    ): JsonResponse {
        $result = $scoring->submit($request->user(), $test, $request->validated('answers'));

        return response()->json([
            'message' => 'Vos réponses ont bien été enregistrées.',
            'result' => UserTestResultResource::make($result),
            'disclaimer' => TestScoringService::DISCLAIMER,
        ], 201);
    }
}
