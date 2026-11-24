<?php

namespace Modules\Notifications\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Appointments\Models\Appointment;

class AppointmentReminderNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly Appointment $appointment) {}

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
        $proName = $this->appointment->professional?->user?->name ?: 'Votre psychologue';
        $time = $this->appointment->scheduled_at->format('d/m/Y à H:i');

        return (new MailMessage)
            ->subject('Rappel de votre rendez-vous Mina Care')
            ->greeting("Bonjour {$notifiable->name},")
            ->line("Ceci est un rappel pour votre consultation prévue le {$time} avec {$proName}.")
            ->lineIf($this->appointment->meeting_link !== null, 'Lien de la consultation vidéo : '.$this->appointment->meeting_link)
            ->action('Voir mon rendez-vous', url('/care/appointments/'.$this->appointment->id))
            ->line('Prenez soin de vous.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'appointment_reminder',
            'appointment_id' => $this->appointment->id,
            'scheduled_at' => $this->appointment->scheduled_at->toIso8601String(),
            'professional_name' => $this->appointment->professional?->user?->name,
            'meeting_link' => $this->appointment->meeting_link,
            'message' => 'Rappel : votre consultation a lieu le '.$this->appointment->scheduled_at->format('d/m/Y à H:i').'.',
        ];
    }
}
