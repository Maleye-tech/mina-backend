<?php

namespace Modules\Assessment\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\Assessment\Http\Controllers\Concerns\InteractsWithETag;
use Modules\Assessment\Http\Requests\IndexFlashcardDecksRequest;
use Modules\Assessment\Http\Resources\FlashcardDeckResource;
use Modules\Assessment\Models\FlashcardDeck;
use Symfony\Component\HttpFoundation\Response;

class FlashcardDeckController extends Controller
{
    use InteractsWithETag;

    /**
     * Nombre de paquets par page par défaut et maximum.
     */
    private const DEFAULT_PER_PAGE = 15;

    private const MAX_PER_PAGE = 100;

    /**
     * Catalogue paginé des paquets publiés, filtrable par matière et niveau.
     *
     * Les brouillons n'apparaissent jamais dans le catalogue ; la consultation
     * d'un paquet précis reste soumise à la policy (les administrateurs
     * peuvent consulter un brouillon directement).
     */
    public function index(IndexFlashcardDecksRequest $request): JsonResponse
    {
        $decks = FlashcardDeck::query()
            ->published()
            ->when(
                $request->validated('subject_id'),
                fn (Builder $query, int $subjectId): Builder => $query->where('subject_id', $subjectId),
            )
            ->when(
                $request->validated('level_id'),
                fn (Builder $query, int $levelId): Builder => $query->where('level_id', $levelId),
            )
            ->with(['subject:id,name,slug', 'level:id,name,slug'])
            ->withCount('flashcards')
            ->latest('id')
            ->paginate(min($request->integer('per_page', self::DEFAULT_PER_PAGE), self::MAX_PER_PAGE));

        return FlashcardDeckResource::collection($decks)->response();
    }

    /**
     * Détail d'un paquet publié (ou d'un brouillon pour un administrateur),
     * avec ses cartes triées par ordre d'affichage.
     *
     * Le contenu d'un paquet est stable : une balise ETag est renvoyée et
     * une requête conditionnelle If-None-Match aboutit à un 304 sans payload.
     */
    public function show(Request $request, FlashcardDeck $deck): Response
    {
        Gate::authorize('view', $deck);

        $deck->loadMissing([
            'subject:id,name,slug',
            'level:id,name,slug',
            'flashcards:id,deck_id,front,back,hint,sort_order',
        ]);

        return $this->respondWithETag(
            ['deck' => FlashcardDeckResource::make($deck)->resolve($request)],
            $request,
        );
    }
}
