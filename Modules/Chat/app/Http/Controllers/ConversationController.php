<?php

namespace Modules\Chat\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Modules\Appointments\Enums\AppointmentStatus;
use Modules\Auth\Enums\UserRole;
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
        Gate::authorize('viewAny', Conversation::class);

        $user = $request->user();

        $conversations = $this->baseQuery($user)
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
            $this->baseQuery($request->user())
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

        if ($user->hasRole(UserRole::Professional)) {
            Gate::authorize('viewAny', Conversation::class);

            $conversation = $this->baseQuery($user)
                ->where('participant_one_id', $one)
                ->where('participant_two_id', $two)
                ->first();

            if ($conversation === null) {
                return response()->json([
                    'message' => 'Cette conversation est introuvable.',
                ], 404);
            }

            Gate::authorize('view', $conversation);

            return response()->json([
                'message' => 'Conversation existante.',
                'data' => ConversationResource::make($conversation),
            ]);
        }

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
     * Oriente automatiquement l'étudiant vers un ambassadeur formé.
     */
    public function requestAmbassador(Request $request): JsonResponse
    {
        $user = $request->user();

        $ambassador = User::query()
            ->where('role', UserRole::Ambassador->value)
            ->where('is_active', true)
            ->where('id', '!=', $user->id)
            ->inRandomOrder()
            ->first();

        if (! $ambassador) {
            return response()->json([
                'message' => 'Aucun ambassadeur disponible pour le moment. Veuillez contacter notre ligne d\'urgence.',
            ], 404);
        }

        [$one, $two] = [$user->id, $ambassador->id];
        [$one, $two] = $one < $two ? [$one, $two] : [$two, $one];

        $conversation = Conversation::firstOrCreate(
            ['participant_one_id' => $one, 'participant_two_id' => $two],
            ['last_message_at' => null, 'is_with_ambassador' => true],
        );

        $conversation->load(['participantOne', 'participantTwo']);

        return response()->json([
            'message' => 'Vous êtes maintenant connecté avec un pair aidant / ambassadeur formé.',
            'data' => ConversationResource::make($conversation),
        ], 200);
    }

    /**
     * @return Builder<Conversation>
     */
    private function baseQuery(User $user): Builder
    {
        $query = Conversation::query()
            ->where(fn (Builder $query) => $query
                ->where('participant_one_id', $user->id)
                ->orWhere('participant_two_id', $user->id))
            ->with(['participantOne', 'participantTwo', 'lastMessage', 'appointment']);

        if ($user->hasRole(UserRole::Professional)) {
            $query->whereHas('appointment', fn (Builder $query) => $query
                ->where('professional_user_id', $user->id)
                ->whereIn('status', [
                    AppointmentStatus::Confirmed->value,
                    AppointmentStatus::Completed->value,
                ]));
        }

        return $query->withCount(['messages as unread_count' => fn (Builder $query) => $query
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')]);
    }
}
