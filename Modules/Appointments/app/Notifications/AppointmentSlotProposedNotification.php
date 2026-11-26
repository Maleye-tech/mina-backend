<?php

namespace Modules\Appointments\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Appointments\Models\Appointment;

class AppointmentSlotProposedNotification extends Notification
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
            ->subject('Mina Care : Un nouveau créneau vous est proposé')
            ->greeting("Bonjour {$notifiable->name},")
            ->line("Le professionnel vous propose un nouveau créneau : {$date}.")
            ->action('Consulter la proposition', rtrim(config('app.frontend_url'), '/').'/espace/accompagnement/'.$this->appointment->id)
            ->line('Vous pouvez accepter ce créneau ou annuler la demande depuis votre espace.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'appointment_slot_proposed',
            'appointment_id' => $this->appointment->id,
            'scheduled_at' => $this->appointment->scheduled_at?->toIso8601String(),
            'message' => 'Un nouveau créneau de rendez-vous vous est proposé.',
        ];
    }
}
