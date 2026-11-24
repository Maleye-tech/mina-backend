<?php

namespace Modules\Notifications\Console;

use Illuminate\Console\Command;
use Modules\Notifications\Notifications\TrialExpiringNotification;
use Modules\Subscriptions\Enums\SubscriptionStatus;
use Modules\Subscriptions\Models\Subscription;

class CheckTrialExpirationsCommand extends Command
{
    protected $signature = 'mina:check-trial-expirations';

    protected $description = 'Avertit les utilisateurs dont l\'essai expire dans 24h et clôture les essais expirés';

    public function handle(): int
    {
        // 1. Send warning for trials expiring within 24h
        $expiringTrials = Subscription::query()
            ->where('status', SubscriptionStatus::Trial->value)
            ->whereBetween('trial_ends_at', [now(), now()->addHours(24)])
            ->with('user')
            ->get();

        $warnedCount = 0;

        foreach ($expiringTrials as $subscription) {
            if ($subscription->user) {
                $subscription->user->notify(new TrialExpiringNotification($subscription));
                $warnedCount++;
            }
        }

        // 2. Mark overdue trials as expired
        $expiredCount = Subscription::query()
            ->where('status', SubscriptionStatus::Trial->value)
            ->where('trial_ends_at', '<', now())
            ->update(['status' => SubscriptionStatus::Expired->value]);

        $this->info("{$warnedCount} rappels d'expiration d'essai envoyés, {$expiredCount} essais clôturés.");

        return Command::SUCCESS;
    }
}
