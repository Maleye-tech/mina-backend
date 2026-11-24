<?php

namespace Modules\Events\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Events\Models\Event;
use Modules\Events\Models\EventRegistration;

class EventService
{
    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Event::query()->published();

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['timeframe'])) {
            if ($filters['timeframe'] === 'upcoming') {
                $query->upcoming()->orderBy('starts_at', 'asc');
            } elseif ($filters['timeframe'] === 'past') {
                $query->past()->orderBy('ends_at', 'desc');
            }
        } else {
            $query->orderBy('starts_at', 'asc');
        }

        return $query->paginate($perPage);
    }

    public function register(User $user, Event $event): EventRegistration
    {
        return DB::transaction(function () use ($user, $event): EventRegistration {
            $lockedEvent = Event::query()->lockForUpdate()->findOrFail($event->id);

            if (! $lockedEvent->is_published) {
                throw ValidationException::withMessages([
                    'event' => ['Cet événement n\'est pas ouvert aux inscriptions.'],
                ]);
            }

            if ($lockedEvent->starts_at->isPast()) {
                throw ValidationException::withMessages([
                    'event' => ['Cet événement a déjà eu lieu ou a déjà commencé.'],
                ]);
            }

            if ($lockedEvent->isFull()) {
                throw ValidationException::withMessages([
                    'event' => ['Désolé, la session a atteint le nombre maximum de participants.'],
                ]);
            }

            $registration = EventRegistration::firstOrCreate(
                ['event_id' => $lockedEvent->id, 'user_id' => $user->id],
                ['status' => 'registered', 'registered_at' => now()]
            );

            if ($registration->wasRecentlyCreated) {
                $lockedEvent->increment('current_participants');
            } elseif ($registration->status === 'cancelled') {
                $registration->update(['status' => 'registered', 'registered_at' => now()]);
                $lockedEvent->increment('current_participants');
            }

            return $registration->load('event');
        });
    }

    public function unregister(User $user, Event $event): bool
    {
        return DB::transaction(function () use ($user, $event): bool {
            $registration = EventRegistration::where('event_id', $event->id)
                ->where('user_id', $user->id)
                ->where('status', 'registered')
                ->first();

            if (! $registration) {
                return false;
            }

            $registration->update(['status' => 'cancelled']);
            $event->decrement('current_participants');

            return true;
        });
    }

    public function userRegistrations(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return EventRegistration::query()
            ->where('user_id', $user->id)
            ->where('status', 'registered')
            ->with('event')
            ->orderBy('registered_at', 'desc')
            ->paginate($perPage);
    }
}
