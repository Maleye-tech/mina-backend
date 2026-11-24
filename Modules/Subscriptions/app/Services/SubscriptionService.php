<?php

namespace Modules\Subscriptions\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Auth\Enums\UserRole;
use Modules\Subscriptions\Enums\SubscriptionStatus;
use Modules\Subscriptions\Models\AccessCode;
use Modules\Subscriptions\Models\Subscription;
use Modules\Subscriptions\Models\SubscriptionPlan;

class SubscriptionService
{
    public const TRIAL_DURATION_DAYS = 3;

    public function currentSubscription(User $user): ?Subscription
    {
        return Subscription::query()
            ->where('user_id', $user->id)
            ->with(['plan', 'partnerInstitution', 'accessCode'])
            ->latest('id')
            ->first();
    }

    public function hasActiveAccess(User $user): bool
    {
        if ($user->hasRole(UserRole::Admin, UserRole::Professional, UserRole::Ambassador)) {
            return true;
        }

        $subscription = $this->currentSubscription($user);

        return $subscription !== null && $subscription->isValid();
    }

    public function canStartTrial(User $user): bool
    {
        return ! Subscription::query()
            ->where('user_id', $user->id)
            ->where(function ($q): void {
                $q->where('status', SubscriptionStatus::Trial->value)
                    ->orWhereNotNull('trial_ends_at');
            })
            ->exists();
    }

    public function startTrial(User $user): Subscription
    {
        if (! $this->canStartTrial($user)) {
            throw ValidationException::withMessages([
                'trial' => ['Vous avez déjà bénéficié de la période d\'essai gratuit de 3 jours.'],
            ]);
        }

        return DB::transaction(function () use ($user): Subscription {
            return Subscription::create([
                'user_id' => $user->id,
                'status' => SubscriptionStatus::Trial,
                'starts_at' => now(),
                'ends_at' => now()->addDays(self::TRIAL_DURATION_DAYS),
                'trial_ends_at' => now()->addDays(self::TRIAL_DURATION_DAYS),
                'auto_renew' => false,
            ]);
        });
    }

    public function redeemCode(User $user, string $code): Subscription
    {
        return DB::transaction(function () use ($user, $code): Subscription {
            $accessCode = AccessCode::query()
                ->where('code', $code)
                ->lockForUpdate()
                ->first();

            if (! $accessCode || ! $accessCode->isValid()) {
                throw ValidationException::withMessages([
                    'code' => ['Ce code d\'accès est invalide, expiré ou déjà utilisé.'],
                ]);
            }

            $durationDays = $accessCode->duration_days ?: 30;
            $partner = $accessCode->partnerInstitution;

            $accessCode->update([
                'is_used' => true,
                'used_at' => now(),
                'user_id' => $user->id,
            ]);

            if ($partner) {
                $partner->increment('current_beneficiaries');
            }

            $subscription = Subscription::create([
                'user_id' => $user->id,
                'plan_id' => $accessCode->plan_id,
                'partner_institution_id' => $partner?->id,
                'access_code_id' => $accessCode->id,
                'status' => SubscriptionStatus::Active,
                'starts_at' => now(),
                'ends_at' => now()->addDays($durationDays),
                'auto_renew' => false,
            ]);

            return $subscription->load(['plan', 'partnerInstitution', 'accessCode']);
        });
    }

    public function activateSubscription(User $user, SubscriptionPlan $plan): Subscription
    {
        return DB::transaction(function () use ($user, $plan): Subscription {
            $startsAt = now();
            $current = $this->currentSubscription($user);

            if ($current && $current->isValid() && $current->ends_at->isFuture()) {
                $startsAt = $current->ends_at;
            }

            return Subscription::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::Active,
                'starts_at' => $startsAt,
                'ends_at' => (clone $startsAt)->addDays($plan->duration_days),
                'auto_renew' => true,
            ]);
        });
    }

    public function cancel(Subscription $subscription): Subscription
    {
        $subscription->update([
            'auto_renew' => false,
            'cancelled_at' => now(),
            'status' => $subscription->ends_at?->isPast() ? SubscriptionStatus::Cancelled : $subscription->status,
        ]);

        return $subscription;
    }
}
