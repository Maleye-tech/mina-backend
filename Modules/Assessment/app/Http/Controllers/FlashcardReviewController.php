<?php

namespace Modules\Assessment\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Modules\Assessment\Enums\ReviewQuality;
use Modules\Assessment\Http\Requests\StoreFlashcardReviewRequest;
use Modules\Assessment\Http\Requests\TodayFlashcardReviewsRequest;
use Modules\Assessment\Http\Resources\FlashcardResource;
use Modules\Assessment\Http\Resources\FlashcardStateResource;
use Modules\Assessment\Models\Flashcard;
use Modules\Assessment\Services\FlashcardReviewService;

class FlashcardReviewController extends Controller
{
    /**
     * Nombre de cartes nouvelles proposées par défaut et maximum.
     */
    private const DEFAULT_NEW_LIMIT = 20;

    private const MAX_NEW_LIMIT = 100;

    public function __construct(private readonly FlashcardReviewService $reviews) {}

    /**
     * Cartes à réviser aujourd'hui : cartes dues puis cartes nouvelles,
     * optionnellement restreintes à un paquet.
     */
    public function today(TodayFlashcardReviewsRequest $request): JsonResponse
    {
        $cards = $this->reviews->cardsDueToday(
            $request->user(),
            $request->validated('deck_id'),
            min($request->integer('new_limit', self::DEFAULT_NEW_LIMIT), self::MAX_NEW_LIMIT),
        );

        return FlashcardResource::collection($cards)->response();
    }

    /**
     * Noter la révision d'une carte (qualité SM-2 de 0 à 5).
     *
     * L'état SM-2 de la carte est recalculé et l'historique des révisions
     * est alimenté ; l'échéance suivante est renvoyée dans la réponse.
     */
    public function store(StoreFlashcardReviewRequest $request, Flashcard $flashcard): JsonResponse
    {
        Gate::authorize('review', $flashcard);

        $review = $this->reviews->review(
            $request->user(),
            $flashcard,
            $request->enum('quality', ReviewQuality::class),
        );

        return response()->json([
            'message' => 'Révision enregistrée.',
            'state' => FlashcardStateResource::make($review['state']),
        ]);
    }
}
