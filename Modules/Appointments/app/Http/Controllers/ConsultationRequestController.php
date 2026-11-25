<?php

namespace Modules\Appointments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Appointments\Http\Requests\ConsultationRequestStatusRequest;
use Modules\Appointments\Http\Resources\ConsultationRequestResource;
use Modules\Appointments\Models\AppointmentProfessional;
use Modules\Appointments\Models\ConsultationRequest;

class ConsultationRequestController extends Controller
{
    /**
     * Liste les demandes de consultation du professionnel connecté.
     */
    public function index(Request $request): JsonResponse
    {
        $professional = $this->currentProfessional($request->user());

        $query = ConsultationRequest::query()
            ->forProfessional($professional->id)
            ->with('student');

        if ($status = $request->string('status')) {
            $query->where('status', $status);
        }

        $perPage = min($request->integer('per_page', 15), 100);

        return response()->json([
            'data' => ConsultationRequestResource::collection($query->paginate($perPage)),
        ]);
    }

    /**
     * Accepte une demande de consultation et crée un rendez-vous.
     */
    public function accept(ConsultationRequestStatusRequest $request, int $id): JsonResponse
    {
        $professional = $this->currentProfessional($request->user());

        $request->validated();

        $consultation = ConsultationRequest::query()
            ->where('professional_id', $professional->id)
            ->whereKey($id)
            ->where('status', 'pending')
            ->firstOrFail();

        $consultation->update([
            'status' => 'accepted',
            'scheduled_for' => $request->input('date') ? Carbon::parse($request->input('date').' '.$request->input('start_time', '09:00')) : null,
            'reason' => $request->input('reason', $consultation->reason),
        ]);

        return response()->json([
            'message' => 'Demande acceptée.',
            'consultation' => ConsultationRequestResource::make($consultation->fresh()),
        ]);
    }

    /**
     * Décline une demande de consultation.
     */
    public function decline(ConsultationRequestStatusRequest $request, int $id): JsonResponse
    {
        $professional = $this->currentProfessional($request->user());

        $consultation = ConsultationRequest::query()
            ->where('professional_id', $professional->id)
            ->whereKey($id)
            ->where('status', 'pending')
            ->firstOrFail();

        $consultation->update([
            'status' => 'declined',
            'reason' => $request->input('reason', $consultation->reason),
        ]);

        return response()->json([
            'message' => 'Demande déclinée.',
            'consultation' => ConsultationRequestResource::make($consultation->fresh()),
        ]);
    }

    private function currentProfessional(User $user): AppointmentProfessional
    {
        $professional = AppointmentProfessional::query()->where('user_id', $user->id)->first();

        if ($professional === null) {
            abort(403, 'Vous devez être un professionnel de santé mentale.');
        }

        return $professional;
    }
}
