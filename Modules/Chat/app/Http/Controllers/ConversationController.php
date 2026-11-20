<?php

namespace Modules\Chat\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Modules\Chat\Http\Requests\IndexConversationsRequest;
use Modules\Chat\Http\Requests\StoreConversationRequest;
use Modules\Chat\Http\Resources\ConversationResource;
use Modules\Chat\Models\Conversation;

class ConversationController extends Controller
{
    private const DEFAULT_PER_PAGE = 15;

    private const MAX_PER_PAGE = 100;

    /**
     * Liste paginée des conversations de l'utilisateur, plus récentes en premier.
     */
    public function index(IndexConversationsRequest $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $conversations = $this->baseQuery($user->id)
            ->orderByRaw('COALESCE(last_message_at, created_at) DESC')
            ->paginate(min($request->integer('per_page', self::DEFAULT_PER_PAGE), self::MAX_PER_PAGE));

        return ConversationResource::collection($conversations);
    }

    /**
     * Détail d'une conversation (participant adverse, dernier message, non-lus).
     */
    public function show(Request $request, Conversation $conversation): ConversationResource
    {
        Gate::authorize('view', $conversation);

        return ConversationResource::make(
            $this->baseQuery($request->user()->id)
                ->whereKey($conversation->id)
                ->firstOrFail(),
        );
    }

    /**
     * Ouvre (ou retrouve) une conversation directe avec un participant.
     */
    public function store(StoreConversationRequest $request): JsonResponse
    {
        $user = $request->user();
        $participantId = $request->integer('participant_id');

        [$one, $two] = [$user->id, $participantId];
        [$one, $two] = $one < $two ? [$one, $two] : [$two, $one];

        $created = false;

        $conversation = Conversation::firstOrCreate(
            ['participant_one_id' => $one, 'participant_two_id' => $two],
            ['last_message_at' => null],
        );

        if ($conversation->wasRecentlyCreated) {
            $created = true;
        }

        $conversation->load(['participantOne', 'participantTwo']);

        return response()->json([
            'message' => $created ? 'Conversation ouverte.' : 'Conversation existante.',
            'data' => ConversationResource::make($conversation),
        ], $created ? 201 : 200);
    }

    /**
     * @return Builder<Conversation>
     */
    private function baseQuery(int $userId): Builder
    {
        return Conversation::query()
            ->where(fn (Builder $query) => $query
                ->where('participant_one_id', $userId)
                ->orWhere('participant_two_id', $userId))
            ->with(['participantOne', 'participantTwo', 'lastMessage'])
            ->withCount(['messages as unread_count' => fn (Builder $query) => $query
                ->where('sender_id', '!=', $userId)
                ->whereNull('read_at')]);
    }
}
