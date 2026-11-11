<?php

namespace Modules\WellBeing\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Modules\WellBeing\Http\Requests\IndexTestResultsRequest;
use Modules\WellBeing\Http\Resources\UserTestResultResource;
use Modules\WellBeing\Models\UserTestResult;

class UserTestResultController extends Controller
{
    private const DEFAULT_PER_PAGE = 15;

    private const MAX_PER_PAGE = 100;

    /**
     * Historique paginé des résultats de l'utilisateur courant.
     */
    public function index(IndexTestResultsRequest $request): AnonymousResourceCollection
    {
        $results = UserTestResult::query()
            ->forUser($request->user())
            ->when(
                $request->validated('test_id'),
                fn ($query, int $testId) => $query->where('test_id', $testId),
            )
            ->with('test')
            ->latest('created_at')
            ->paginate(min($request->integer('per_page', self::DEFAULT_PER_PAGE), self::MAX_PER_PAGE));

        return UserTestResultResource::collection($results);
    }

    /**
     * Détail d'un résultat : propriétaire ou administrateur uniquement.
     */
    public function show(UserTestResult $result): UserTestResultResource
    {
        Gate::authorize('view', $result);

        $result->load(['test', 'user']);

        return UserTestResultResource::make($result);
    }
}
