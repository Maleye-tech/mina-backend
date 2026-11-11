<?php

namespace Modules\WellBeing\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Modules\WellBeing\Http\Requests\IndexArticlesRequest;
use Modules\WellBeing\Http\Resources\WellbeingArticleResource;
use Modules\WellBeing\Models\WellbeingArticle;

class WellbeingArticleController extends Controller
{
    private const DEFAULT_PER_PAGE = 15;

    private const MAX_PER_PAGE = 100;

    /**
     * Catalogue paginé des fiches de prévention publiées.
     */
    public function index(IndexArticlesRequest $request): AnonymousResourceCollection
    {
        $articles = WellbeingArticle::query()
            ->published()
            ->when(
                $request->validated('category'),
                fn ($query, string $category) => $query->where('category', $category),
            )
            ->orderBy('title')
            ->paginate(min($request->integer('per_page', self::DEFAULT_PER_PAGE), self::MAX_PER_PAGE));

        return WellbeingArticleResource::collection($articles);
    }

    /**
     * Détail d'une fiche publiée (ou d'un brouillon pour un administrateur).
     */
    public function show(WellbeingArticle $article): WellbeingArticleResource
    {
        Gate::authorize('view', $article);

        return WellbeingArticleResource::make($article);
    }
}
