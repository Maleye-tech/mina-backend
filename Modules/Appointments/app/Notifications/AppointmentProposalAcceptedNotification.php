<?php

namespace Modules\Appointments\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Appointments\Models\Appointment;

class AppointmentProposalAcceptedNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly Appointment $appointment) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $date = $this->appointment->scheduled_at?->translatedFormat('l d F Y à H:i') ?? 'à convenir';

        return (new MailMessage)
            ->subject('Mina Care : Le créneau proposé a été accepté')
            ->greeting("Bonjour {$notifiable->name},")
            ->line("L’étudiant a accepté le créneau proposé : {$date}.")
            ->action('Voir le rendez-vous', rtrim(config('app.frontend_url'), '/').'/pro/demandes');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'appointment_proposal_accepted',
            'appointment_id' => $this->appointment->id,
            'scheduled_at' => $this->appointment->scheduled_at?->toIso8601String(),
            'message' => 'L’étudiant a accepté le nouveau créneau proposé.',
        ];
    }
}
