<?php

namespace Modules\Assessment\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\Assessment\Enums\Difficulty;
use Modules\Assessment\Http\Controllers\Concerns\InteractsWithETag;
use Modules\Assessment\Http\Requests\IndexClinicalCasesRequest;
use Modules\Assessment\Http\Resources\ClinicalCaseResource;
use Modules\Assessment\Models\ClinicalCase;
use Symfony\Component\HttpFoundation\Response;

class ClinicalCaseController extends Controller
{
    use InteractsWithETag;

    /**
     * Nombre de cas cliniques par page par défaut et maximum.
     */
    private const DEFAULT_PER_PAGE = 15;

    private const MAX_PER_PAGE = 100;

    /**
     * Catalogue paginé des cas cliniques publiés, filtrable par matière,
     * niveau et difficulté.
     *
     * Le contenu des étapes et les corrigés ne sont jamais exposés ici :
     * ils ne sont révélés qu'au fil d'une tentative.
     */
    public function index(IndexClinicalCasesRequest $request): JsonResponse
    {
        $clinicalCases = ClinicalCase::query()
            ->published()
            ->when(
                $request->validated('subject_id'),
                fn (Builder $query, int $subjectId): Builder => $query->where('subject_id', $subjectId),
            )
            ->when(
                $request->validated('level_id'),
                fn (Builder $query, int $levelId): Builder => $query->where('level_id', $levelId),
            )
            ->when(
                $request->enum('difficulty', Difficulty::class),
                fn (Builder $query, Difficulty $difficulty): Builder => $query->where('difficulty', $difficulty->value),
            )
            ->with(['subject:id,name,slug', 'level:id,name,slug'])
            ->withCount('steps')
            ->latest('id')
            ->paginate(min($request->integer('per_page', self::DEFAULT_PER_PAGE), self::MAX_PER_PAGE));

        return ClinicalCaseResource::collection($clinicalCases)->response();
    }

    /**
     * Détail d'un cas clinique publié (ou d'un brouillon pour un
     * administrateur), sans le contenu des étapes.
     *
     * Le contenu d'un cas est stable : une balise ETag est renvoyée et une
     * requête conditionnelle If-None-Match aboutit à un 304 sans payload.
     */
    public function show(Request $request, ClinicalCase $clinicalCase): Response
    {
        Gate::authorize('view', $clinicalCase);

        $clinicalCase->loadMissing(['subject:id,name,slug', 'level:id,name,slug'])->loadCount('steps');

        return $this->respondWithETag(
            ['clinical_case' => ClinicalCaseResource::make($clinicalCase)->resolve($request)],
            $request,
        );
    }
}
