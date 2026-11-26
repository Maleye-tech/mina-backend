<?php

namespace Modules\Appointments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Appointments\Http\Requests\StoreAvailabilitySlotRequest;
use Modules\Appointments\Http\Requests\UpdateAvailabilitySlotRequest;
use Modules\Appointments\Http\Resources\AvailabilitySlotResource;
use Modules\Appointments\Models\AvailabilitySlot;

class ProfessionalAvailabilityController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $slots = AvailabilitySlot::query()
            ->where('professional_id', $request->user()->id)
            ->orderBy('starts_at')
            ->get();

        return AvailabilitySlotResource::collection($slots);
    }

    public function store(StoreAvailabilitySlotRequest $request): JsonResponse
    {
        $user = $request->user();
        $startsAt = $request->date('starts_at');
        $endsAt = $request->date('ends_at');

        $slot = DB::transaction(function () use ($user, $request, $startsAt, $endsAt): AvailabilitySlot {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $this->ensureNoOverlap($user->id, $startsAt, $endsAt);

            return AvailabilitySlot::query()->create([
                'professional_id' => $user->id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'mode' => $request->input('mode'),
                'is_booked' => false,
            ]);
        });

        return response()->json([
            'message' => 'Créneau de disponibilité ajouté avec succès.',
            'slot' => AvailabilitySlotResource::make($slot),
        ], 201);
    }

    public function update(UpdateAvailabilitySlotRequest $request, int $id): JsonResponse
    {
        $user = $request->user();

        $slot = DB::transaction(function () use ($request, $user, $id): AvailabilitySlot {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $slot = AvailabilitySlot::query()
                ->where('professional_id', $user->id)
                ->whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($slot->is_booked) {
                abort(422, 'Impossible de modifier un créneau déjà réservé par un étudiant.');
            }

            $startsAt = $request->filled('starts_at') ? $request->date('starts_at') : $slot->starts_at;
            $endsAt = $request->filled('ends_at') ? $request->date('ends_at') : $slot->ends_at;

            if ($endsAt->lessThanOrEqualTo($startsAt)) {
                throw ValidationException::withMessages([
                    'ends_at' => ['L’heure de fin doit être postérieure à l’heure de début.'],
                ]);
            }

            $this->ensureNoOverlap($user->id, $startsAt, $endsAt, $slot->id);
            $slot->update($request->validated());

            return $slot;
        });

        return response()->json([
            'message' => 'Créneau mis à jour.',
            'slot' => AvailabilitySlotResource::make($slot),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        DB::transaction(function () use ($user, $id): void {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $slot = AvailabilitySlot::query()
                ->where('professional_id', $user->id)
                ->whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($slot->is_booked) {
                abort(422, 'Impossible de supprimer un créneau déjà réservé par un étudiant.');
            }

            $slot->delete();
        });

        return response()->json([
            'message' => 'Créneau supprimé avec succès.',
        ]);
    }

    private function ensureNoOverlap(int $professionalId, $startsAt, $endsAt, ?int $exceptId = null): void
    {
        $query = AvailabilitySlot::query()
            ->where('professional_id', $professionalId)
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt);

        if ($exceptId !== null) {
            $query->whereKeyNot($exceptId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'starts_at' => ['Ce créneau chevauche une disponibilité déjà enregistrée.'],
            ]);
        }
    }
}
