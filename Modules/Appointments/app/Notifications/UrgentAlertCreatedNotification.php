<?php

namespace Modules\Appointments\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Appointments\Models\UrgentAlert;

class UrgentAlertCreatedNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly UrgentAlert $alert) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Mina Care : Alerte d’urgence déclenchée par un étudiant')
            ->greeting("Bonjour {$notifiable->name},")
            ->line("Un étudiant vient de déclencher le protocole d'aide d'urgence sur Mina Care.")
            ->action('Consulter les alertes', rtrim(config('app.frontend_url'), '/').'/pro/alertes')
            ->line('Merci de vérifier immédiatement cette alerte selon le protocole de veille.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'urgent_alert',
            'alert_id' => $this->alert->id,
            'message' => 'Une alerte d’urgence a été déclenchée.',
        ];
    }
}
