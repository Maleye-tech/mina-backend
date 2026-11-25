<?php

namespace Modules\Appointments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Appointments\Http\Requests\AvailabilityRequest;
use Modules\Appointments\Http\Requests\BookAppointmentRequest;
use Modules\Appointments\Http\Requests\CancelAppointmentRequest;
use Modules\Appointments\Http\Requests\RescheduleAppointmentRequest;
use Modules\Appointments\Http\Resources\AppointmentAvailabilityResource;
use Modules\Appointments\Http\Resources\AppointmentProfessionalResource;
use Modules\Appointments\Http\Resources\AppointmentResource;
use Modules\Appointments\Http\Resources\AppointmentSlotResource;
use Modules\Appointments\Models\Appointment;
use Modules\Appointments\Models\AppointmentAvailability;
use Modules\Appointments\Models\AppointmentProfessional;
use Modules\Appointments\Services\AppointmentService;
use Modules\Auth\Enums\UserRole;

class AppointmentsController extends Controller
{
    public function __construct(private readonly AppointmentService $appointments) {}

    public function professionals(Request $request): AnonymousResourceCollection
    {
        $perPage = min($request->integer('per_page', 15), 50);

        return AppointmentProfessionalResource::collection($this->appointments->listProfessionals($perPage));
    }

    public function slots(int $professionalId): AnonymousResourceCollection
    {
        return AppointmentSlotResource::collection($this->appointments->listAvailableSlots($professionalId));
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min($request->integer('per_page', 15), 50);

        return AppointmentResource::collection($this->appointments->listForUser($request->user(), $perPage));
    }

    public function store(BookAppointmentRequest $request): JsonResponse
    {
        $appointment = $this->appointments->book(
            $request->user(),
            $request->integer('professional_id'),
            $request->integer('slot_id'),
            $request->input('consultation_type', 'video'),
            $request->input('student_notes')
        );

        return response()->json([
            'message' => 'Rendez-vous réservé avec succès.',
            'appointment' => AppointmentResource::make($appointment->load(['professional.user', 'student'])),
        ], 201);
    }

    public function show(Request $request, Appointment $appointment): AppointmentResource
    {
        $user = $request->user();

        if ($appointment->student_id !== $user->id && $appointment->professional->user_id !== $user->id && ! $user->hasRole(UserRole::Admin)) {
            abort(403, 'Accès non autorisé à ce rendez-vous.');
        }

        return AppointmentResource::make($appointment->load(['professional.user', 'student']));
    }

    public function cancel(CancelAppointmentRequest $request, Appointment $appointment): JsonResponse
    {
        $user = $request->user();

        if ($appointment->student_id !== $user->id && $appointment->professional->user_id !== $user->id && ! $user->hasRole(UserRole::Admin)) {
            abort(403, 'Accès non autorisé.');
        }

        $cancelled = $this->appointments->cancel(
            $appointment,
            $user,
            $request->string('reason')->toString()
        );

        return response()->json([
            'message' => 'Rendez-vous annulé.',
            'appointment' => AppointmentResource::make($cancelled->load(['professional.user', 'student'])),
        ]);
    }

    public function reschedule(RescheduleAppointmentRequest $request, Appointment $appointment): JsonResponse
    {
        $user = $request->user();

        if ($appointment->student_id !== $user->id && $appointment->professional->user_id !== $user->id && ! $user->hasRole(UserRole::Admin)) {
            abort(403, 'Accès non autorisé.');
        }

        $rescheduled = $this->appointments->reschedule(
            $appointment,
            $user,
            $request->integer('new_slot_id')
        );

        return response()->json([
            'message' => 'Rendez-vous reporté avec succès.',
            'appointment' => AppointmentResource::make($rescheduled->load(['professional.user', 'student'])),
        ]);
    }

    /**
     * Disponibilités du professionnel connecté.
     */
    public function availability(Request $request): AnonymousResourceCollection
    {
        $professional = $this->currentProfessional($request->user());

        return AppointmentAvailabilityResource::collection(
            AppointmentAvailability::query()
                ->where('professional_id', $professional->id)
                ->orderBy('weekday')
                ->orderBy('start_time')
                ->get()
        );
    }

    /**
     * Créer une disponibilité pour le professionnel connecté.
     */
    public function storeAvailability(AvailabilityRequest $request): JsonResponse
    {
        $professional = $this->currentProfessional($request->user());

        $availability = AppointmentAvailability::create([
            'professional_id' => $professional->id,
            'weekday' => $request->integer('weekday'),
            'start_time' => $request->input('start_time'),
            'end_time' => $request->input('end_time'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return response()->json([
            'message' => 'Disponibilité créée avec succès.',
            'availability' => AppointmentAvailabilityResource::make($availability),
        ], 201);
    }

    /**
     * Modifier une disponibilité du professionnel connecté.
     */
    public function updateAvailability(AvailabilityRequest $request, int $id): JsonResponse
    {
        $professional = $this->currentProfessional($request->user());

        $availability = AppointmentAvailability::query()
            ->where('professional_id', $professional->id)
            ->whereKey($id)
            ->firstOrFail();

        $availability->update([
            'start_time' => $request->input('start_time', $availability->start_time),
            'end_time' => $request->input('end_time', $availability->end_time),
            'is_active' => $request->boolean('is_active', $availability->is_active),
        ]);

        return response()->json([
            'message' => 'Disponibilité mise à jour.',
            'availability' => AppointmentAvailabilityResource::make($availability->fresh()),
        ]);
    }

    /**
     * Supprimer une disponibilité du professionnel connecté.
     */
    public function destroyAvailability(Request $request, int $id): JsonResponse
    {
        $professional = $this->currentProfessional($request->user());

        $availability = AppointmentAvailability::query()
            ->where('professional_id', $professional->id)
            ->whereKey($id)
            ->firstOrFail();

        $availability->delete();

        return response()->json([
            'message' => 'Disponibilité supprimée.',
        ]);
    }

    /**
     * Récupère le profil professionnel de l'utilisateur connecté.
     *
     * @throws HttpResponseException 403 si l'utilisateur n'est pas un professionnel.
     */
    private function currentProfessional(?User $user): AppointmentProfessional
    {
        $professional = $user?->professionalProfile;

        if ($professional === null) {
            abort(403, 'Vous devez être un professionnel de santé mentale pour gérer vos disponibilités.');
        }

        return $professional;
    }
}
