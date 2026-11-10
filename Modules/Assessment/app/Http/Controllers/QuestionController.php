<?php

namespace Modules\Assessment\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\Assessment\Enums\Difficulty;
use Modules\Assessment\Http\Controllers\Concerns\InteractsWithETag;
use Modules\Assessment\Http\Requests\IndexQuestionsRequest;
use Modules\Assessment\Http\Resources\QuestionResource;
use Modules\Assessment\Models\Question;
use Symfony\Component\HttpFoundation\Response;

class QuestionController extends Controller
{
    use InteractsWithETag;

    /**
     * Nombre de questions par page par défaut et maximum.
     */
    private const DEFAULT_PER_PAGE = 15;

    private const MAX_PER_PAGE = 100;

    /**
     * Catalogue paginé des questions publiées, filtrable par matière, niveau et difficulté.
     *
     * Les brouillons n'apparaissent jamais dans le catalogue ; la consultation
     * d'une question précise reste soumise à la policy (les administrateurs
     * peuvent consulter un brouillon directement).
     */
    public function index(IndexQuestionsRequest $request): JsonResponse
    {
        $questions = Question::query()
            ->published()
            ->when(
                $request->validated('subject_id'),
                fn (Builder $query, int $subjectId): Builder => $query->where('subject_id', $subjectId),
            )
            ->when(
                $request->validated('level_id'),
                fn (Builder $query, int $levelId): Builder => $query->where('level_id', $levelId),
            )
            ->when(
                $request->enum('difficulty', Difficulty::class),
                fn (Builder $query, Difficulty $difficulty): Builder => $query->ofDifficulty($difficulty),
            )
            ->with(['subject:id,name,slug', 'level:id,name,slug'])
            ->latest('id')
            ->paginate(min($request->integer('per_page', self::DEFAULT_PER_PAGE), self::MAX_PER_PAGE));

        return QuestionResource::collection($questions)->response();
    }

    /**
     * Détail d'une question publiée (ou d'un brouillon pour un administrateur).
     *
     * Le contenu d'une question est stable : une balise ETag est renvoyée et
     * une requête conditionnelle If-None-Match aboutit à un 304 sans payload.
     * La correction et l'explication ne sont jamais exposées ici.
     */
    public function show(Request $request, Question $question): Response
    {
        Gate::authorize('view', $question);

        $question->loadMissing(['subject:id,name,slug', 'level:id,name,slug', 'options']);

        return $this->respondWithETag(
            ['question' => QuestionResource::make($question)->resolve($request)],
            $request,
        );
    }
}
