<?php

namespace Modules\Library\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Library\Models\Content;
use Modules\Library\Models\Level;
use Modules\Library\Models\Podcast;
use Modules\Library\Models\Subject;
use Modules\Library\Support\SignedUrl;

class OfflineManifestController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $contents = Content::query()
            ->published()
            ->with(['subject:id,name', 'level:id,name'])
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Content $content): array => [
                'id' => $content->id,
                'title' => $content->title,
                'description' => $content->description,
                'type' => $content->type->value,
                'type_label' => $content->type->label(),
                'subject_id' => $content->subject_id,
                'level_id' => $content->level_id,
                'body' => $content->body,
                'sort_order' => $content->sort_order,
            ]);

        $podcasts = Podcast::query()
            ->published()
            ->whereHas('favorites', fn ($query) => $query->where('user_id', $userId))
            ->with(['subject:id,name', 'level:id,name'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Podcast $podcast): array => [
                'id' => $podcast->id,
                'title' => $podcast->title,
                'description' => $podcast->description,
                'subject_id' => $podcast->subject_id,
                'level_id' => $podcast->level_id,
                'duration_seconds' => $podcast->duration_seconds,
                'size_bytes' => $podcast->size_bytes,
                'audio_url' => SignedUrl::for($podcast->audioPath()),
            ]);

        $favoriteContents = $request->user()->favorites()
            ->where('favoritable_type', Content::class)
            ->pluck('favoritable_id');

        $favoritePodcasts = $request->user()->favorites()
            ->where('favoritable_type', Podcast::class)
            ->pluck('favoritable_id');

        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'signed_url_ttl_minutes' => (int) config('library.signed_url_ttl_minutes'),
            'subjects' => Subject::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'color', 'sort_order']),
            'levels' => Level::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'sort_order']),
            'contents' => $contents,
            'podcasts' => $podcasts,
            'favorites' => [
                'contents' => $favoriteContents->map(fn ($id): array => ['id' => $id])->values(),
                'podcasts' => $favoritePodcasts->map(fn ($id): array => ['id' => $id])->values(),
            ],
        ]);
    }
}
