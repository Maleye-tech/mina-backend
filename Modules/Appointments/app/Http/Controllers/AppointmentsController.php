<?php

namespace Modules\Appointments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Modules\Appointments\Enums\AppointmentStatus;
use Modules\Appointments\Http\Requests\AvailabilityRequest;
use Modules\Appointments\Http\Requests\BookAppointmentRequest;
use Modules\Appointments\Http\Requests\CancelAppointmentRequest;
use Modules\Appointments\Http\Requests\CompleteAppointmentRequest;
use Modules\Appointments\Http\Requests\ConfirmAppointmentRequest;
use Modules\Appointments\Http\Requests\DeclineAppointmentRequest;
use Modules\Appointments\Http\Requests\MeetingUrlRequest;
use Modules\Appointments\Http\Requests\RescheduleAppointmentRequest;
use Modules\Appointments\Http\Resources\AppointmentAvailabilityResource;
use Modules\Appointments\Http\Resources\AppointmentProfessionalResource;
use Modules\Appointments\Http\Resources\AppointmentResource;
use Modules\Appointments\Http\Resources\AppointmentSlotResource;
use Modules\Appointments\Models\Appointment;
use Modules\Appointments\Models\AppointmentAvailability;
use Modules\Appointments\Models\AppointmentProfessional;
use Modules\Appointments\Notifications\AppointmentConfirmedNotification;
use Modules\Appointments\Notifications\AppointmentDeclinedNotification;
use Modules\Appointments\Notifications\AppointmentRequestNotification;
use Modules\Appointments\Services\AppointmentService;
use Modules\Auth\Enums\UserRole;

class AppointmentsController extends Controller
{
    public function __construct(private readonly AppointmentService $appointments) {}

    public function professionals(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ], [
            'per_page.integer' => 'Le nombre d’éléments par page doit être un entier.',
            'per_page.min' => 'Le nombre d’éléments par page doit être au moins 1.',
            'per_page.max' => 'Le nombre d’éléments par page ne peut pas dépasser 50.',
        ]);

        $perPage = min($request->integer('per_page', 15), 50);

        return AppointmentProfessionalResource::collection($this->appointments->listProfessionals($perPage));
    }

    public function slots(int $professionalId): AnonymousResourceCollection
    {
        return AppointmentSlotResource::collection($this->appointments->listAvailableSlots($professionalId));
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'status' => ['nullable', Rule::enum(AppointmentStatus::class)],
        ], [
            'per_page.integer' => 'Le nombre d’éléments par page doit être un entier.',
            'per_page.min' => 'Le nombre d’éléments par page doit être au moins 1.',
            'per_page.max' => 'Le nombre d’éléments par page ne peut pas dépasser 50.',
            'status.enum' => 'Le statut demandé n’est pas valide.',
        ]);

        $perPage = min($request->integer('per_page', 15), 50);
        $status = $request->string('status');

        $query = Appointment::query()->with(['student', 'professional.user', 'slot']);
        $user = $request->user();

        if ($user->hasRole(UserRole::Professional)) {
            $pro = AppointmentProfessional::where('user_id', $user->id)->first();
            $query->where('professional_id', $pro?->id ?? 0);
        } elseif ($user->hasRole(UserRole::Student)) {
            $query->where('student_id', $user->id);
        } elseif (! $user->hasRole(UserRole::Admin)) {
            abort(403, 'Accès non autorisé.');
        }

        if ($status->isNotEmpty()) {
            $query->where('status', $status->toString());
        }

        return AppointmentResource::collection($query->orderBy('scheduled_at', 'desc')->paginate($perPage));
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

        $professionalUser = $appointment->professional->user;
        Notification::send($professionalUser, new AppointmentRequestNotification($appointment));

        return response()->json([
            'message' => 'Rendez-vous réservé avec succès.',
            'appointment' => AppointmentResource::make($appointment->load(['professional.user', 'student'])),
        ], 201);
    }

    public function show(Request $request, Appointment $appointment): AppointmentResource
    {
        Gate::authorize('view', $appointment);

        return AppointmentResource::make($appointment->load(['professional.user', 'student']));
    }

    public function confirm(ConfirmAppointmentRequest $request, Appointment $appointment): JsonResponse
    {
        Gate::authorize('confirm', $appointment);

        $updated = $this->appointments->confirm(
            $appointment,
            $request->user(),
            $request->input('meeting_url')
        );

        $updated->update(['responded_at' => now()]);

        Notification::send($updated->student, new AppointmentConfirmedNotification($updated->fresh()));

        return response()->json([
            'message' => 'Rendez-vous confirmé.',
            'appointment' => AppointmentResource::make($updated->fresh()->load(['professional.user', 'student'])),
        ]);
    }

    public function decline(DeclineAppointmentRequest $request, Appointment $appointment): JsonResponse
    {
        Gate::authorize('decline', $appointment);

        $appointment->update([
            'status' => 'declined',
            'responded_at' => now(),
            'cancellation_reason' => $request->input('reason'),
        ]);

        Notification::send($appointment->student, new AppointmentDeclinedNotification($appointment->fresh()));

        return response()->json([
            'message' => 'Rendez-vous décliné.',
            'appointment' => AppointmentResource::make($appointment->fresh()->load(['professional.user', 'student'])),
        ]);
    }

    public function complete(CompleteAppointmentRequest $request, Appointment $appointment): JsonResponse
    {
        Gate::authorize('complete', $appointment);

        $appointment->update([
            'status' => 'completed',
            'responded_at' => now(),
        ]);

        return response()->json([
            'message' => 'Séance clôturée.',
            'appointment' => AppointmentResource::make($appointment->fresh()->load(['professional.user', 'student'])),
        ]);
    }

    public function meetingUrl(MeetingUrlRequest $request, Appointment $appointment): JsonResponse
    {
        Gate::authorize('update', $appointment);

        $appointment->update([
            'meeting_url' => $request->input('meeting_url'),
        ]);

        return response()->json([
            'message' => 'Lien de visio mis à jour.',
            'appointment' => AppointmentResource::make($appointment->fresh()->load(['professional.user', 'student'])),
        ]);
    }

    public function cancel(CancelAppointmentRequest $request, Appointment $appointment): JsonResponse
    {
        Gate::authorize('cancel', $appointment);

        $cancelled = $this->appointments->cancel(
            $appointment,
            $request->user(),
            $request->string('reason')->toString()
        );

        return response()->json([
            'message' => 'Rendez-vous annulé.',
            'appointment' => AppointmentResource::make($cancelled->load(['professional.user', 'student'])),
        ]);
    }

    public function reschedule(RescheduleAppointmentRequest $request, Appointment $appointment): JsonResponse
    {
        Gate::authorize('cancel', $appointment);

        $rescheduled = $this->appointments->reschedule(
            $appointment,
            $request->user(),
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

        if (! $professional->canOpenSlots()) {
            return response()->json([
                'message' => 'Votre profil doit être vérifié et la charte acceptée avant d’ouvrir des créneaux.',
            ], 403);
        }

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

    private function currentProfessional(?User $user): AppointmentProfessional
    {
        $professional = $user?->professionalProfile;

        abort_unless(
            $user?->is_active && $professional?->canOpenSlots(),
            403,
            'Votre profil doit être vérifié et votre charte acceptée pour gérer vos disponibilités.',
        );

        return $professional;
    }
}
