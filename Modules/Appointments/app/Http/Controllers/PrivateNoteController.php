<?php

namespace Modules\Appointments\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\Appointments\Http\Requests\PrivateNoteRequest;
use Modules\Appointments\Http\Resources\PrivateNoteResource;
use Modules\Appointments\Models\Appointment;
use Modules\Appointments\Models\PrivateNote;
use Modules\Chat\Models\Conversation;

class PrivateNoteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = PrivateNote::query()->where('user_id', $request->user()->id)
            ->latest('created_at');

        $perPage = min($request->integer('per_page', 15), 100);

        return response()->json([
            'data' => PrivateNoteResource::collection($query->paginate($perPage)),
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $note = PrivateNote::query()
            ->where('user_id', $request->user()->id)
            ->whereKey($id)
            ->firstOrFail();

        return response()->json([
            'note' => PrivateNoteResource::make($note),
        ]);
    }

    public function store(PrivateNoteRequest $request): JsonResponse
    {
        if ($request->filled('appointment_id')) {
            Gate::authorize('view', Appointment::query()->findOrFail($request->integer('appointment_id')));
        }

        if ($request->filled('conversation_id')) {
            Gate::authorize('view', Conversation::query()->findOrFail($request->integer('conversation_id')));
        }

        $note = PrivateNote::create([
            'user_id' => $request->user()->id,
            'appointment_id' => $request->input('appointment_id'),
            'conversation_id' => $request->input('conversation_id'),
            'body' => $request->input('body'),
        ]);

        return response()->json([
            'message' => 'Note privée créée.',
            'note' => PrivateNoteResource::make($note),
        ], 201);
    }

    public function update(PrivateNoteRequest $request, int $id): JsonResponse
    {
        $note = PrivateNote::query()
            ->where('user_id', $request->user()->id)
            ->whereKey($id)
            ->firstOrFail();

        $note->update([
            'body' => $request->input('body', $note->body),
        ]);

        return response()->json([
            'message' => 'Note privée mise à jour.',
            'note' => PrivateNoteResource::make($note->fresh()),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $note = PrivateNote::query()
            ->where('user_id', $request->user()->id)
            ->whereKey($id)
            ->firstOrFail();

        $note->delete();

        return response()->json([
            'message' => 'Note privée supprimée.',
        ]);
    }
}
