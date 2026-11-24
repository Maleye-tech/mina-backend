<?php

namespace Modules\Events\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Events\Http\Resources\EventRegistrationResource;
use Modules\Events\Http\Resources\EventResource;
use Modules\Events\Models\Event;
use Modules\Events\Services\EventService;

class EventsController extends Controller
{
    public function __construct(private readonly EventService $events) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min($request->integer('per_page', 15), 50);

        $filters = [
            'type' => $request->string('type')->toString(),
            'timeframe' => $request->string('timeframe')->toString(),
        ];

        return EventResource::collection($this->events->list($filters, $perPage));
    }

    public function show(Event $event): EventResource
    {
        return EventResource::make($event);
    }

    public function register(Request $request, Event $event): JsonResponse
    {
        $registration = $this->events->register($request->user(), $event);

        return response()->json([
            'message' => 'Inscription à l\'événement confirmée.',
            'registration' => EventRegistrationResource::make($registration),
        ], 201);
    }

    public function unregister(Request $request, Event $event): JsonResponse
    {
        $unregistered = $this->events->unregister($request->user(), $event);

        return response()->json([
            'message' => $unregistered ? 'Inscription annulée.' : 'Vous n\'étiez pas inscrit à cet événement.',
        ]);
    }

    public function myRegistrations(Request $request): AnonymousResourceCollection
    {
        $perPage = min($request->integer('per_page', 15), 50);

        return EventRegistrationResource::collection($this->events->userRegistrations($request->user(), $perPage));
    }
}
