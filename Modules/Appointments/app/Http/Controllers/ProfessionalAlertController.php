<?php

namespace Modules\Appointments\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\Appointments\Enums\AlertStatus;
use Modules\Appointments\Http\Resources\UrgentAlertResource;
use Modules\Appointments\Models\UrgentAlert;

class ProfessionalAlertController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', UrgentAlert::class);

        $request->validate([
            'status' => ['nullable', Rule::enum(AlertStatus::class)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ], [
            'status.enum' => 'Le statut demandé n’est pas valide.',
            'per_page.integer' => 'Le nombre d’éléments par page doit être un entier.',
            'per_page.min' => 'Le nombre d’éléments par page doit être au moins 1.',
            'per_page.max' => 'Le nombre d’éléments par page ne peut pas dépasser 50.',
        ]);

        $query = UrgentAlert::query()
            ->with(['student', 'handler'])
            ->latest('created_at');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return UrgentAlertResource::collection(
            $query->paginate(min($request->integer('per_page', 20), 50)),
        );
    }

    public function handle(Request $request, int $id): JsonResponse
    {
        $alert = DB::transaction(function () use ($request, $id): UrgentAlert {
            $alert = UrgentAlert::query()->whereKey($id)->lockForUpdate()->firstOrFail();
            Gate::authorize('handle', $alert);
            $alert->markAsHandled($request->user());

            return $alert;
        });

        return response()->json([
            'message' => 'L’alerte a été marquée comme prise en charge avec succès.',
            'alert' => UrgentAlertResource::make($alert->fresh(['student', 'handler'])),
        ]);
    }
}
