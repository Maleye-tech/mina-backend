<?php

namespace Modules\Assessment\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\Assessment\Http\Requests\IndexClinicalCaseAttemptsRequest;
use Modules\Assessment\Http\Requests\StartClinicalCaseAttemptRequest;
use Modules\Assessment\Http\Requests\SubmitClinicalCaseStepAnswerRequest;
use Modules\Assessment\Http\Resources\ClinicalCaseAttemptResource;
use Modules\Assessment\Models\ClinicalCase;
use Modules\Assessment\Models\ClinicalCaseAttempt;
use Modules\Assessment\Services\ClinicalCaseService;

class ClinicalCaseAttemptController extends Controller
{
    /**
     * Nombre de tentatives par page par défaut et maximum.
     */
    private const DEFAULT_PER_PAGE = 15;

    private const MAX_PER_PAGE = 100;

    public function __construct(private readonly ClinicalCaseService $cases) {}

    /**
     * Historique paginé des tentatives de cas cliniques de l'utilisatrice ou
     * de l'utilisateur courant.
     */
    public function index(IndexClinicalCaseAttemptsRequest $request): JsonResponse
    {
        $attempts = $this->cases->userAttempts(
            $request->user(),
            min($request->integer('per_page', self::DEFAULT_PER_PAGE), self::MAX_PER_PAGE),
        );

        return ClinicalCaseAttemptResource::collection($attempts)->response();
    }

    /**
     * Démarrer une tentative de cas clinique.
     *
     * La réponse expose la présentation du cas et la première étape ;
     * les étapes suivantes sont révélées au fil de la progression.
     */
    public function start(StartClinicalCaseAttemptRequest $request): JsonResponse
    {
        $clinicalCase = ClinicalCase::query()->findOrFail($request->validated('clinical_case_id'));

        Gate::authorize('view', $clinicalCase);

        $attempt = $this->cases->startAttempt($request->user(), $clinicalCase);

        return response()->json([
            'message' => 'Tentative de cas clinique démarrée.',
            'attempt' => ClinicalCaseAttemptResource::make($attempt),
        ], 201);
    }

    /**
     * État d'une tentative : étapes répondues avec leurs corrigés, étape
     * courante, et étapes restantes masquées.
     */
    public function show(Request $request, ClinicalCaseAttempt $attempt): JsonResponse
    {
        Gate::authorize('view', $attempt);

        return response()->json([
            'attempt' => ClinicalCaseAttemptResource::make($this->cases->loadForDisplay($attempt)),
        ]);
    }

    /**
     * Soumettre la réponse à l'étape courante du cas clinique.
     *
     * La réponse devient immuable et le corrigé de l'étape est révélé ;
     * la réponse renvoie l'état complet de la tentative, avec l'étape
     * suivante dévoilée.
     */
    public function answer(SubmitClinicalCaseStepAnswerRequest $request, ClinicalCaseAttempt $attempt): JsonResponse
    {
        Gate::authorize('answer', $attempt);

        $attempt = $this->cases->submitStepAnswer(
            $request->user(),
            $attempt,
            (int) $request->validated('step_id'),
            (string) $request->validated('answer'),
        );

        return response()->json([
            'message' => 'Réponse enregistrée.',
            'attempt' => ClinicalCaseAttemptResource::make($attempt),
        ]);
    }

    /**
     * Terminer la tentative.
     *
     * Les corrigés de toutes les étapes — y compris celles sans réponse —
     * sont alors révélés.
     */
    public function finish(Request $request, ClinicalCaseAttempt $attempt): JsonResponse
    {
        Gate::authorize('finish', $attempt);

        $completed = $this->cases->finish($request->user(), $attempt);

        return response()->json([
            'message' => 'Cas clinique terminé.',
            'attempt' => ClinicalCaseAttemptResource::make($completed),
        ]);
    }
}
