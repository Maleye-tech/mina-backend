<?php

namespace Modules\Chat\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Chat\Http\Requests\StoreMessageRequest;
use Modules\Chat\Http\Resources\MessageResource;
use Modules\Chat\Models\Conversation;

class MessageController extends Controller
{
    private const DEFAULT_PER_PAGE = 20;

    private const MAX_PER_PAGE = 100;

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

        $message = DB::transaction(function () use ($request, $conversation) {
            $message = $conversation->messages()->create([
                'sender_id' => $request->user()->id,
                'body' => $request->string('body')->toString(),
            ]);

            $conversation->update(['last_message_at' => $message->created_at]);

            return $message;
        });

        return response()->json([
            'message' => 'Message envoyé.',
            'data' => MessageResource::make($message),
        ], 201);
    }
}
