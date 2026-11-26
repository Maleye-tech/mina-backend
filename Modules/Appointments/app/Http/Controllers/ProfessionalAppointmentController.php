<?php

namespace Modules\Appointments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\Appointments\Enums\AppointmentStatus;
use Modules\Appointments\Http\Requests\ProposeAppointmentSlotRequest;
use Modules\Appointments\Http\Requests\SaveSessionNoteRequest;
use Modules\Appointments\Http\Requests\SendCareMessageRequest;
use Modules\Appointments\Http\Requests\UpdateAppointmentMeetingUrlRequest;
use Modules\Appointments\Http\Resources\CareAppointmentResource;
use Modules\Appointments\Http\Resources\CareMessageResource;
use Modules\Appointments\Http\Resources\SessionNoteResource;
use Modules\Appointments\Models\Appointment;
use Modules\Appointments\Models\AvailabilitySlot;
use Modules\Appointments\Models\SessionNote;
use Modules\Appointments\Notifications\AppointmentConfirmedNotification;
use Modules\Appointments\Notifications\AppointmentDeclinedNotification;
use Modules\Appointments\Notifications\AppointmentSlotProposedNotification;
use Modules\Appointments\Services\AppointmentConversationService;
use Modules\Chat\Models\Conversation;
use Modules\Chat\Models\Message;

class ProfessionalAppointmentController extends Controller
{
    public function __construct(private readonly AppointmentConversationService $conversations) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'status' => ['nullable', Rule::enum(AppointmentStatus::class)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ], [
            'status.enum' => 'Le statut demandé n’est pas valide.',
            'per_page.integer' => 'Le nombre d’éléments par page doit être un entier.',
            'per_page.min' => 'Le nombre d’éléments par page doit être au moins 1.',
            'per_page.max' => 'Le nombre d’éléments par page ne peut pas dépasser 50.',
        ]);

        $query = Appointment::query()
            ->with(['student', 'availabilitySlot', 'professionalProfile'])
            ->where('professional_user_id', $request->user()->id);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return CareAppointmentResource::collection(
            $query->orderBy('scheduled_at', 'desc')->paginate(min($request->integer('per_page', 20), 50)),
        );
    }

    public function show(Request $request, int $id): CareAppointmentResource
    {
        $appointment = $this->professionalAppointment($request->user(), $id);
        Gate::authorize('view', $appointment);

        return CareAppointmentResource::make($appointment);
    }

    public function confirm(Request $request, int $id): JsonResponse
    {
        $appointment = DB::transaction(function () use ($request, $id): Appointment {
            $appointment = $this->lockedProfessionalAppointment($request->user(), $id);
            Gate::authorize('confirm', $appointment);

            if (! in_array($appointment->status, [AppointmentStatus::Requested, AppointmentStatus::Pending], true)) {
                abort(422, 'Seule une demande en attente peut être confirmée.');
            }

            $appointment->update([
                'status' => AppointmentStatus::Confirmed,
                'responded_at' => now(),
            ]);

            $this->conversations->forAppointment($appointment);

            return $appointment;
        });

        $appointment->student?->notify(new AppointmentConfirmedNotification($appointment));

        return response()->json([
            'message' => 'Rendez-vous confirmé. La messagerie avec l’étudiant est ouverte.',
            'appointment' => CareAppointmentResource::make($appointment->fresh(['student', 'availabilitySlot', 'professionalProfile'])),
        ]);
    }

    public function decline(Request $request, int $id): JsonResponse
    {
        $appointment = DB::transaction(function () use ($request, $id): Appointment {
            $appointment = $this->lockedProfessionalAppointment($request->user(), $id);
            Gate::authorize('decline', $appointment);

            if (! in_array($appointment->status, [AppointmentStatus::Requested, AppointmentStatus::Pending, AppointmentStatus::Proposed], true)) {
                abort(422, 'Seule une demande en attente peut être déclinée.');
            }

            $appointment->update([
                'status' => AppointmentStatus::Declined,
                'responded_at' => now(),
            ]);

            $appointment->availabilitySlot?->update(['is_booked' => false]);
            $appointment->slot?->update(['is_booked' => false]);

            return $appointment;
        });

        $appointment->student?->notify(new AppointmentDeclinedNotification($appointment));

        return response()->json([
            'message' => 'Le rendez-vous a été décliné et le créneau a été libéré.',
            'appointment' => CareAppointmentResource::make($appointment->fresh(['student', 'availabilitySlot', 'professionalProfile'])),
        ]);
    }

    public function propose(ProposeAppointmentSlotRequest $request, int $id): JsonResponse
    {
        $appointment = DB::transaction(function () use ($request, $id): Appointment {
            $user = $request->user();
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            $appointment = Appointment::query()
                ->where('professional_user_id', $user->id)
                ->whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();
            Gate::authorize('update', $appointment);

            if (! in_array($appointment->status, [AppointmentStatus::Requested, AppointmentStatus::Proposed], true)) {
                abort(422, 'Un nouveau créneau ne peut être proposé que pour une demande en attente.');
            }

            $slot = AvailabilitySlot::query()
                ->where('professional_id', $user->id)
                ->whereKey($request->integer('availability_slot_id'))
                ->lockForUpdate()
                ->firstOrFail();

            if ($slot->is_booked || $slot->starts_at->isPast() || $slot->id === $appointment->availability_slot_id) {
                abort(422, 'Ce créneau horaire n’est plus disponible.');
            }

            $appointment->availabilitySlot?->update(['is_booked' => false]);
            $slot->update(['is_booked' => true]);
            $appointment->update([
                'availability_slot_id' => $slot->id,
                'scheduled_at' => $slot->starts_at,
                'duration_minutes' => max(1, $slot->starts_at->diffInMinutes($slot->ends_at)),
                'consultation_type' => $slot->mode->value,
                'status' => AppointmentStatus::Proposed,
                'responded_at' => now(),
            ]);

            return $appointment;
        });

        $appointment->student?->notify(new AppointmentSlotProposedNotification($appointment));

        return response()->json([
            'message' => 'Le nouveau créneau a été proposé à l’étudiant.',
            'appointment' => CareAppointmentResource::make($appointment->fresh(['student', 'availabilitySlot', 'professionalProfile'])),
        ]);
    }

    public function complete(Request $request, int $id): JsonResponse
    {
        $appointment = DB::transaction(function () use ($request, $id): Appointment {
            $appointment = $this->lockedProfessionalAppointment($request->user(), $id);
            Gate::authorize('complete', $appointment);

            if (! $appointment->isConfirmed()) {
                abort(422, 'Seul un rendez-vous confirmé peut être marqué comme terminé.');
            }

            $appointment->update(['status' => AppointmentStatus::Completed]);

            return $appointment;
        });

        return response()->json([
            'message' => 'La séance est marquée comme terminée.',
            'appointment' => CareAppointmentResource::make($appointment->fresh(['student', 'availabilitySlot', 'professionalProfile'])),
        ]);
    }

    public function updateMeetingUrl(UpdateAppointmentMeetingUrlRequest $request, int $id): JsonResponse
    {
        $appointment = DB::transaction(function () use ($request, $id): Appointment {
            $appointment = $this->lockedProfessionalAppointment($request->user(), $id);
            Gate::authorize('updateMeetingUrl', $appointment);

            $appointment->update([
                'meeting_url' => $request->input('meeting_url'),
                'meeting_link' => $request->input('meeting_url'),
            ]);

            return $appointment;
        });

        return response()->json([
            'message' => 'Le lien de visioconférence a été mis à jour avec succès.',
            'appointment' => CareAppointmentResource::make($appointment->fresh(['student', 'availabilitySlot', 'professionalProfile'])),
        ]);
    }

    public function messages(Request $request, int $id): AnonymousResourceCollection
    {
        $user = $request->user();
        $appointment = $this->professionalAppointment($user, $id);
        Gate::authorize('messages', $appointment);

        $conversation = $this->conversations->forAppointment($appointment);

        Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return CareMessageResource::collection(
            Message::query()
                ->where('conversation_id', $conversation->id)
                ->orderBy('created_at')
                ->get(),
        );
    }

    public function sendMessage(SendCareMessageRequest $request, int $id): JsonResponse
    {
        $user = $request->user();

        $message = DB::transaction(function () use ($request, $user, $id): Message {
            $appointment = $this->lockedProfessionalAppointment($user, $id);
            Gate::authorize('messages', $appointment);

            if (! $appointment->isConfirmed()) {
                abort(403, 'Le tchat est fermé après la fin de la séance. Vous pouvez consulter les messages existants.');
            }

            $conversation = $this->conversations->forAppointment($appointment);
            Conversation::query()->whereKey($conversation->id)->lockForUpdate()->firstOrFail();

            $message = Message::query()->create([
                'conversation_id' => $conversation->id,
                'sender_id' => $user->id,
                'body' => $request->input('body'),
            ]);

            $conversation->update(['last_message_at' => now()]);

            return $message;
        });

        return response()->json([
            'message' => 'Message envoyé.',
            'data' => CareMessageResource::make($message),
        ], 201);
    }

    public function getNote(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $appointment = $this->professionalAppointment($user, $id);
        $this->ensureSessionStarted($appointment);

        $note = SessionNote::query()
            ->where('appointment_id', $appointment->id)
            ->where('professional_id', $user->id)
            ->first();

        if ($note) {
            Gate::authorize('view', $note);
        }

        return response()->json([
            'note' => $note ? SessionNoteResource::make($note) : null,
        ]);
    }

    public function saveNote(SaveSessionNoteRequest $request, int $id): JsonResponse
    {
        $user = $request->user();

        $note = DB::transaction(function () use ($request, $user, $id): SessionNote {
            $appointment = $this->lockedProfessionalAppointment($user, $id);
            $this->ensureSessionStarted($appointment);

            $existingNote = SessionNote::query()
                ->where('appointment_id', $appointment->id)
                ->where('professional_id', $user->id)
                ->lockForUpdate()
                ->first();

            Gate::authorize($existingNote ? 'update' : 'create', $existingNote ?? SessionNote::class);

            return SessionNote::query()->updateOrCreate(
                [
                    'appointment_id' => $appointment->id,
                    'professional_id' => $user->id,
                ],
                ['body' => $request->input('body')],
            );
        });

        return response()->json([
            'message' => 'Note confidentielle de séance enregistrée.',
            'note' => SessionNoteResource::make($note),
        ]);
    }

    private function professionalAppointment(User $user, int $id): Appointment
    {
        return Appointment::query()
            ->with(['student', 'availabilitySlot', 'professionalProfile'])
            ->where('professional_user_id', $user->id)
            ->findOrFail($id);
    }

    private function lockedProfessionalAppointment(User $user, int $id): Appointment
    {
        User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

        return Appointment::query()
            ->with(['student', 'availabilitySlot', 'professionalProfile'])
            ->where('professional_user_id', $user->id)
            ->whereKey($id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function ensureSessionStarted(Appointment $appointment): void
    {
        if (! $appointment->isConfirmed() && ! $appointment->isCompleted()) {
            abort(403, 'Les notes sont accessibles uniquement après la confirmation du rendez-vous.');
        }
    }
}
