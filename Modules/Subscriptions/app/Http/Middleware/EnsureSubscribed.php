<?php

namespace Modules\Subscriptions\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Subscriptions\Services\SubscriptionService;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscribed
{
    public function __construct(private readonly SubscriptionService $subscriptions) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Non authentifié.',
            ], 401);
        }

        if (! $this->subscriptions->hasActiveAccess($user)) {
            return response()->json([
                'code' => 'subscription_required',
                'message' => "Un abonnement actif ou une période d'essai valide est requis pour accéder à cette fonctionnalité.",
                'can_start_trial' => $this->subscriptions->canStartTrial($user),
            ], 403);
        }

        return $next($request);
    }
}
