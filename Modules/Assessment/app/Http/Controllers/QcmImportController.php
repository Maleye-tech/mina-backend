<?php

namespace Modules\Assessment\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Modules\Assessment\Enums\ImportStatus;
use Modules\Assessment\Http\Requests\IndexQcmImportsRequest;
use Modules\Assessment\Http\Requests\StoreQcmImportRequest;
use Modules\Assessment\Http\Resources\QcmImportResource;
use Modules\Assessment\Jobs\ProcessQcmImport;
use Modules\Assessment\Models\QcmImport;

class QcmImportController extends Controller
{
    /**
     * Nombre d’imports par page par défaut et maximum.
     */
    private const DEFAULT_PER_PAGE = 15;

    private const MAX_PER_PAGE = 100;

    /**
     * Historique paginé des imports, filtrable par statut.
     */
    public function index(IndexQcmImportsRequest $request): JsonResponse
    {
        $imports = QcmImport::query()
            ->when(
                $request->enum('status', ImportStatus::class),
                fn (Builder $query, ImportStatus $status): Builder => $query->where('status', $status),
            )
            ->with('user:id,name,email')
            ->latest('id')
            ->paginate(min($request->integer('per_page', self::DEFAULT_PER_PAGE), self::MAX_PER_PAGE));

        return QcmImportResource::collection($imports)->response();
    }

    /**
     * Enregistrer le fichier CSV et lancer son traitement en arrière-plan.
     */
    public function store(StoreQcmImportRequest $request): JsonResponse
    {
        $file = $request->file('file');
        $path = $file->store('assessment-imports', 'local');

        $import = QcmImport::create([
            'user_id' => $request->user()->id,
            'filename' => $file->getClientOriginalName(),
            'file_path' => $path,
            'status' => ImportStatus::Pending,
            'dry_run' => $request->boolean('dry_run'),
        ]);

        ProcessQcmImport::dispatch($import);

        return response()->json([
            'message' => 'Import de QCM lancé.',
            'import' => QcmImportResource::make($import->load('user:id,name,email')),
        ], 202);
    }

    /**
     * Détail et avancement d’un import.
     */
    public function show(QcmImport $import): JsonResponse
    {
        return response()->json([
            'import' => QcmImportResource::make($import->load('user:id,name,email')),
        ]);
    }
}
