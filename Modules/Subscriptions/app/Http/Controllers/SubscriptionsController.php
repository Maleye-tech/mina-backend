<?php

namespace Modules\Subscriptions\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Subscriptions\Http\Requests\RedeemCodeRequest;
use Modules\Subscriptions\Http\Resources\SubscriptionPlanResource;
use Modules\Subscriptions\Http\Resources\SubscriptionResource;
use Modules\Subscriptions\Models\SubscriptionPlan;
use Modules\Subscriptions\Services\SubscriptionService;

class SubscriptionsController extends Controller
{
    public function __construct(private readonly SubscriptionService $subscriptions) {}

    public function plans(): JsonResponse
    {
        $plans = SubscriptionPlan::query()->active()->orderBy('price')->get();

        return response()->json([
            'trial_days' => SubscriptionService::TRIAL_DURATION_DAYS,
            'plans' => SubscriptionPlanResource::collection($plans),
        ]);
    }

    public function current(Request $request): JsonResponse
    {
        $user = $request->user();
        $subscription = $this->subscriptions->currentSubscription($user);

        return response()->json([
            'has_active_access' => $this->subscriptions->hasActiveAccess($user),
            'can_start_trial' => $this->subscriptions->canStartTrial($user),
            'subscription' => $subscription ? SubscriptionResource::make($subscription) : null,
        ]);
    }

    public function startTrial(Request $request): JsonResponse
    {
        $subscription = $this->subscriptions->startTrial($request->user());

        return response()->json([
            'message' => 'Votre essai gratuit de 3 jours est activé.',
            'subscription' => SubscriptionResource::make($subscription),
        ], 201);
    }

    public function redeemCode(RedeemCodeRequest $request): JsonResponse
    {
        $subscription = $this->subscriptions->redeemCode(
            $request->user(),
            $request->string('code')->trim()->toString()
        );

        return response()->json([
            'message' => 'Code d\'accès validé avec succès. Votre abonnement est actif.',
            'subscription' => SubscriptionResource::make($subscription),
        ]);
    }

    public function cancel(Request $request): JsonResponse
    {
        $subscription = $this->subscriptions->currentSubscription($request->user());

        if (! $subscription || ! $subscription->isValid()) {
            return response()->json([
                'message' => 'Aucun abonnement actif à résilier.',
            ], 422);
        }

        $this->subscriptions->cancel($subscription);

        return response()->json([
            'message' => 'Le renouvellement automatique de votre abonnement a été désactivé.',
            'subscription' => SubscriptionResource::make($subscription->fresh()),
        ]);
    }
}
