<?php

namespace Modules\Chat\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Chat\Events\MessageSent;
use Modules\Chat\Http\Requests\StoreMessageRequest;
use Modules\Chat\Http\Resources\MessageResource;
use Modules\Chat\Models\ChatReport;
use Modules\Chat\Models\Conversation;
use Modules\Chat\Models\Message;
use Modules\Chat\Services\CrisisDetectorService;

class MessageController extends Controller
{
    private const DEFAULT_PER_PAGE = 20;

    private const MAX_PER_PAGE = 100;

    public function __construct(private readonly CrisisDetectorService $crisisDetector) {}

    /**
     * Messages paginés (plus récents en premier).
     *
     * La consultation marque les messages reçus comme lus afin de mettre à
     * jour le compteur de non-lus de la conversation.
     */
    public function index(Request $request, Conversation $conversation): AnonymousResourceCollection
    {
        Gate::authorize('view', $conversation);

        $user = $request->user();

        $conversation->messages()
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $messages = $conversation->messages()
            ->with('sender')
            ->orderByDesc('id')
            ->paginate(min($request->integer('per_page', self::DEFAULT_PER_PAGE), self::MAX_PER_PAGE));

        return MessageResource::collection($messages);
    }

    /**
     * Envoi d'un message dans la conversation.
     */
    public function store(StoreMessageRequest $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('send', $conversation);

        $body = $request->string('body')->toString();
        $crisisAnalysis = $this->crisisDetector->analyze($body);

        $message = DB::transaction(function () use ($request, $conversation, $body, $crisisAnalysis) {
            $message = $conversation->messages()->create([
                'sender_id' => $request->user()->id,
                'body' => $body,
                'is_crisis' => $crisisAnalysis['is_crisis'],
                'crisis_keywords' => $crisisAnalysis['keywords'],
            ]);

            $conversationData = ['last_message_at' => $message->created_at];

            if ($crisisAnalysis['is_crisis']) {
                $conversationData['crisis_flagged_at'] = now();
            }

            $conversation->update($conversationData);

            return $message;
        });

        // Broadcast to Reverb
        event(new MessageSent($message->load('sender')));

        $response = [
            'message' => 'Message envoyé.',
            'data' => MessageResource::make($message->load('sender')),
        ];

        if ($crisisAnalysis['is_crisis']) {
            $response['crisis_alert'] = [
                'message' => 'Si vous traversez un moment difficile, vous n\'êtes pas seul. Des professionnels et des numéros d\'urgence gratuits sont à votre disposition 24h/24.',
                'contacts' => $crisisAnalysis['emergency_contacts'],
            ];
        }

        return response()->json($response, 201);
    }

    /**
     * Suppression d'un message (Droit à l'effacement).
     */
    public function destroy(Request $request, Conversation $conversation, Message $message): JsonResponse
    {
        Gate::authorize('view', $conversation);

        if ($message->sender_id !== $request->user()->id && ! $request->user()->hasRole(\Modules\Auth\Enums\UserRole::Admin)) {
            abort(403, 'Vous ne pouvez supprimer que vos propres messages.');
        }

        $message->delete();

        return response()->json([
            'message' => 'Message supprimé.',
        ]);
    }

    /**
     * Signalement d'un message pour modération.
     */
    public function report(Request $request, Conversation $conversation, Message $message): JsonResponse
    {
        Gate::authorize('view', $conversation);

        $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $report = ChatReport::create([
            'reporter_id' => $request->user()->id,
            'message_id' => $message->id,
            'conversation_id' => $conversation->id,
            'reason' => $request->string('reason')->toString(),
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Signalement enregistré. Notre équipe de modération va examiner ce message.',
            'report_id' => $report->id,
        ], 201);
    }
}
