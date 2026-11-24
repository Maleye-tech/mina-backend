<?php

namespace Modules\Appointments\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Appointments\Http\Requests\BookAppointmentRequest;
use Modules\Appointments\Http\Requests\CancelAppointmentRequest;
use Modules\Appointments\Http\Requests\RescheduleAppointmentRequest;
use Modules\Appointments\Http\Resources\AppointmentProfessionalResource;
use Modules\Appointments\Http\Resources\AppointmentResource;
use Modules\Appointments\Http\Resources\AppointmentSlotResource;
use Modules\Appointments\Models\Appointment;
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
}
