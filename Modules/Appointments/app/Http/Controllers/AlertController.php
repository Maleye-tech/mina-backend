<?php

namespace Modules\Appointments\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Appointments\Http\Requests\AlertStatusRequest;
use Modules\Appointments\Http\Resources\AlertResource;
use Modules\Appointments\Models\Alert;

class AlertController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Alert::query()->where('user_id', $request->user()->id);

        if ($status = $request->string('status')) {
            $query->where('status', $status);
        }

        $perPage = min($request->integer('per_page', 15), 100);

        return response()->json([
            'data' => AlertResource::collection($query->latest('created_at')->paginate($perPage)),
        ]);
    }

    public function update(AlertStatusRequest $request, int $id): JsonResponse
    {
        $alert = Alert::query()
            ->where('user_id', $request->user()->id)
            ->whereKey($id)
            ->firstOrFail();

        $alert->update([
            'status' => $request->input('status'),
        ]);

        return response()->json([
            'message' => 'Alerte mise à jour.',
            'alert' => AlertResource::make($alert->fresh()),
        ]);
    }
}
