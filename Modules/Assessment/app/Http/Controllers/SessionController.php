<?php

namespace Modules\Assessment\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\Assessment\Http\Requests\IndexSessionsRequest;
use Modules\Assessment\Http\Requests\StartSessionRequest;
use Modules\Assessment\Http\Requests\SubmitAnswerRequest;
use Modules\Assessment\Http\Resources\AttemptQuestionResource;
use Modules\Assessment\Http\Resources\AttemptResource;
use Modules\Assessment\Models\Attempt;
use Modules\Assessment\Services\QcmSessionService;

class SessionController extends Controller
{
    /**
     * Nombre de sessions par page par défaut et maximum.
     */
    private const DEFAULT_PER_PAGE = 15;

    private const MAX_PER_PAGE = 100;

    public function __construct(private readonly QcmSessionService $sessions) {}

    /**
     * Historique paginé des sessions de l'utilisatrice ou de l'utilisateur courant.
     */
    public function index(IndexSessionsRequest $request): JsonResponse
    {
        $attempts = $this->sessions->userAttempts(
            $request->user(),
            min($request->integer('per_page', self::DEFAULT_PER_PAGE), self::MAX_PER_PAGE),
        );

        return AttemptResource::collection($attempts)->response();
    }

    /**
     * Démarrer une session de QCM : tirage aléatoire de questions publiées
     * correspondant aux critères et fige le plan de réponses.
     */
    public function start(StartSessionRequest $request): JsonResponse
    {
        $attempt = $this->sessions->startSession($request->user(), $request->validated());

        return response()->json([
            'message' => 'Session de QCM démarrée.',
            'attempt' => AttemptResource::make($attempt),
        ], 201);
    }

    /**
     * État d'une session : questions, réponses déjà soumises et corrections révélées.
     */
    public function show(Request $request, Attempt $attempt): JsonResponse
    {
        Gate::authorize('view', $attempt);

        return response()->json([
            'attempt' => AttemptResource::make($this->sessions->loadForDisplay($attempt)),
        ]);
    }

    /**
     * Soumettre la réponse à une question de la session.
     *
     * La réponse est corrigée immédiatement puis devient immuable ; la
     * correction renvoyée contient la bonne réponse et l'explication.
     */
    public function answer(SubmitAnswerRequest $request, Attempt $attempt): JsonResponse
    {
        Gate::authorize('answer', $attempt);

        $answer = $this->sessions->submitAnswer(
            $request->user(),
            $attempt,
            (int) $request->validated('question_id'),
            $request->validated('option_ids'),
        );

        return response()->json([
            'message' => 'Réponse enregistrée.',
            'answer' => AttemptQuestionResource::make($answer),
        ]);
    }

    /**
     * Terminer la session et calculer le score.
     *
     * Les questions sans réponse sont comptées comme incorrectes.
     */
    public function finish(Request $request, Attempt $attempt): JsonResponse
    {
        Gate::authorize('finish', $attempt);

        $completed = $this->sessions->finish($request->user(), $attempt);

        return response()->json([
            'message' => 'Session terminée.',
            'attempt' => AttemptResource::make($completed),
        ]);
    }
}
