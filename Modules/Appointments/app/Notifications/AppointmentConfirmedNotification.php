<?php

namespace Modules\Appointments\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Appointments\Models\Appointment;

class AppointmentConfirmedNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly Appointment $appointment) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $slotDate = $this->appointment->scheduled_at?->translatedFormat('l d F Y à H:i') ?? 'Non spécifiée';

        $mail = (new MailMessage)
            ->subject('Mina Care : Votre rendez-vous est confirmé !')
            ->greeting("Bonjour {$notifiable->name},")
            ->line('Votre praticien a confirmé votre créneau de consultation.')
            ->line("Date : {$slotDate}")
            ->line("Mode : {$this->appointment->consultation_type}");

        if ($this->appointment->meeting_url) {
            $mail->line("Lien de visioconférence : {$this->appointment->meeting_url}")
                ->action('Accéder à la visio', $this->appointment->meeting_url);
        } else {
            $mail->action('Voir mon rendez-vous', rtrim(config('app.frontend_url'), '/').'/espace/accompagnement/'.$this->appointment->id);
        }

        return $mail->line('La messagerie sécurisée est désormais ouverte.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'appointment_confirmed',
            'appointment_id' => $this->appointment->id,
            'scheduled_at' => $this->appointment->scheduled_at?->toIso8601String(),
            'meeting_url' => $this->appointment->meeting_url,
            'message' => 'Votre rendez-vous a été confirmé par le professionnel.',
        ];
    }
}
