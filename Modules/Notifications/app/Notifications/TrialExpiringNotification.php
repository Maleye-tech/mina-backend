<?php

namespace Modules\Notifications\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Subscriptions\Models\Subscription;

class TrialExpiringNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly Subscription $subscription) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($notifiable->notifications_enabled && $notifiable->email_notifications) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $endsAt = $this->subscription->trial_ends_at?->format('d/m/Y à H:i') ?: 'demain';

        return (new MailMessage)
            ->subject('Votre période d\'essai Mina se termine bientôt')
            ->greeting("Bonjour {$notifiable->name},")
            ->line("Votre période d'essai gratuit de 3 jours arrive à échéance le {$endsAt}.")
            ->line('Pour continuer à profiter de toutes vos préparations aux examens, QCM, fiches et ateliers santé mentale, abonnez-vous à partir de 3 000 FCFA/mois ou 15 000 FCFA/an.')
            ->action('Choisir mon abonnement', url('/subscriptions/plans'))
            ->line('Merci de faire confiance à Mina pour vos études.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'trial_expiring',
            'subscription_id' => $this->subscription->id,
            'trial_ends_at' => $this->subscription->trial_ends_at?->toIso8601String(),
            'message' => 'Votre essai gratuit de 3 jours expire bientôt. Choisissez votre formule pour continuer sans interruption.',
        ];
    }
}
