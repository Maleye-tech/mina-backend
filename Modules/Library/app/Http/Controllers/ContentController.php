<?php

namespace Modules\Library\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Library\Enums\ContentType;
use Modules\Library\Models\Content;

class ContentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'search' => ['sometimes', 'string', 'max:255'],
            'subject_id' => ['sometimes', 'integer', 'exists:subjects,id'],
            'level_id' => ['sometimes', 'integer', 'exists:levels,id'],
            'type' => ['sometimes', 'string', Rule::in(array_column(ContentType::cases(), 'value'))],
            'favorite' => ['sometimes', 'boolean'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Content::query()
            ->published()
            ->with(['subject:id,name', 'level:id,name'])
            ->withExists(['favorites as favorited' => fn ($builder) => $builder->where('user_id', $request->user()->id)])
            ->orderBy('sort_order')
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

        if (isset($data['type'])) {
            $query->where('type', $data['type']);
        }

        if ($request->boolean('favorite')) {
            $query->whereHas('favorites', fn ($builder) => $builder->where('user_id', $request->user()->id));
        }

        $contents = $query
            ->paginate($data['per_page'] ?? 15)
            ->through(fn (Content $content): array => $this->present($content));

        return response()->json($contents);
    }

    public function show(Request $request, Content $content): JsonResponse
    {
        abort_unless($content->is_published, 404);

        $content->load(['subject:id,name', 'level:id,name']);

        $favorited = $request->user()->favorites()
            ->where('favoritable_type', Content::class)
            ->where('favoritable_id', $content->id)
            ->exists();

        return response()->json([
            'content' => $this->present($content, withBody: true, favorited: $favorited),
        ]);
    }

    public function favorite(Request $request, Content $content): JsonResponse
    {
        $request->user()->favorites()->firstOrCreate([
            'favoritable_type' => Content::class,
            'favoritable_id' => $content->id,
        ]);

        return response()->json([
            'message' => 'Ajouté à vos favoris.',
            'favorited' => true,
        ]);
    }

    public function unfavorite(Request $request, Content $content): JsonResponse
    {
        $request->user()->favorites()
            ->where('favoritable_type', Content::class)
            ->where('favoritable_id', $content->id)
            ->delete();

        return response()->json([
            'message' => 'Retiré de vos favoris.',
            'favorited' => false,
        ]);
    }

    private function present(Content $content, bool $withBody = false, ?bool $favorited = null): array
    {
        $data = [
            'id' => $content->id,
            'title' => $content->title,
            'description' => $content->description,
            'type' => $content->type->value,
            'type_label' => $content->type->label(),
            'subject' => $content->subject?->only(['id', 'name']),
            'level' => $content->level?->only(['id', 'name']),
            'sort_order' => $content->sort_order,
            'is_published' => $content->is_published,
            'favorited' => $favorited ?? (bool) ($content->favorited ?? false),
            'created_at' => $content->created_at,
        ];

        if ($withBody) {
            $data['body'] = $content->body;
        }

        return $data;
    }
}
