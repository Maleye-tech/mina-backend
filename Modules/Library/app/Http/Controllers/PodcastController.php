<?php

namespace Modules\Library\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Library\Http\Requests\StorePodcastRequest;
use Modules\Library\Models\Podcast;
use Modules\Library\Support\SignedUrl;

class PodcastController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'search' => ['sometimes', 'string', 'max:255'],
            'subject_id' => ['sometimes', 'integer', 'exists:subjects,id'],
            'level_id' => ['sometimes', 'integer', 'exists:levels,id'],
            'favorite' => ['sometimes', 'boolean'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Podcast::query()
            ->published()
            ->with(['subject:id,name', 'level:id,name'])
            ->withExists(['favorites as favorited' => fn ($builder) => $builder->where('user_id', $request->user()->id)])
            ->orderByDesc('created_at');

        if ($search = trim($data['search'] ?? '')) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (isset($data['subject_id'])) {
            $query->where('subject_id', $data['subject_id']);
        }

        if (isset($data['level_id'])) {
            $query->where('level_id', $data['level_id']);
        }

        if ($request->boolean('favorite')) {
            $query->whereHas('favorites', fn ($builder) => $builder->where('user_id', $request->user()->id));
        }

        $podcasts = $query
            ->paginate($data['per_page'] ?? 15)
            ->through(fn (Podcast $podcast): array => $this->present($podcast));

        return response()->json($podcasts);
    }

    public function show(Request $request, Podcast $podcast): JsonResponse
    {
        abort_unless($podcast->is_published, 404);

        $podcast->load(['subject:id,name', 'level:id,name']);

        $favorited = $request->user()->favorites()
            ->where('favoritable_type', Podcast::class)
            ->where('favoritable_id', $podcast->id)
            ->exists();

        return response()->json([
            'podcast' => $this->present($podcast, favorited: $favorited),
        ]);
    }

    public function store(StorePodcastRequest $request): JsonResponse
    {
        $data = $request->validated();

        $file = $request->file('audio');
        $path = $file->store(
            config('library.originals_directory'),
            config('library.audio_disk'),
        );

        $podcast = Podcast::create([
            'subject_id' => $data['subject_id'],
            'level_id' => $data['level_id'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'original_path' => $path,
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize() ?: 0,
            'uploaded_by' => $request->user()->id,
            'is_published' => $data['is_published'] ?? false,
        ]);

        $podcast->load(['subject:id,name', 'level:id,name']);

        return response()->json([
            'message' => 'Podcast envoyé. La compression est mise en file d’attente.',
            'podcast' => $this->present($podcast),
        ], 201);
    }

    public function favorite(Request $request, Podcast $podcast): JsonResponse
    {
        $request->user()->favorites()->firstOrCreate([
            'favoritable_type' => Podcast::class,
            'favoritable_id' => $podcast->id,
        ]);

        return response()->json([
            'message' => 'Ajouté à vos favoris.',
            'favorited' => true,
        ]);
    }

    public function unfavorite(Request $request, Podcast $podcast): JsonResponse
    {
        $request->user()->favorites()
            ->where('favoritable_type', Podcast::class)
            ->where('favoritable_id', $podcast->id)
            ->delete();

        return response()->json([
            'message' => 'Retiré de vos favoris.',
            'favorited' => false,
        ]);
    }

    private function present(Podcast $podcast, ?bool $favorited = null): array
    {
        return [
            'id' => $podcast->id,
            'title' => $podcast->title,
            'description' => $podcast->description,
            'subject' => $podcast->subject?->only(['id', 'name']),
            'level' => $podcast->level?->only(['id', 'name']),
            'duration_seconds' => $podcast->duration_seconds,
            'mime_type' => $podcast->mime_type,
            'size_bytes' => $podcast->size_bytes,
            'compression_status' => $podcast->compression_status->value,
            'compression_status_label' => $podcast->compression_status->label(),
            'is_published' => $podcast->is_published,
            'favorited' => $favorited ?? (bool) ($podcast->favorited ?? false),
            'audio_url' => SignedUrl::for($podcast->audioPath()),
            'created_at' => $podcast->created_at,
        ];
    }
}
