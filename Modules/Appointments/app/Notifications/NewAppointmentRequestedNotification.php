<?php

namespace Modules\Appointments\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Appointments\Models\Appointment;

class NewAppointmentRequestedNotification extends Notification
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

        return (new MailMessage)
            ->subject('Mina Care : Nouvelle demande de rendez-vous reçue')
            ->greeting("Bonjour {$notifiable->name},")
            ->line('Un étudiant vient de réserver un créneau de consultation avec vous.')
            ->line("Date prévue : {$slotDate}")
            ->line("Mode : {$this->appointment->consultation_type}")
            ->action('Gérer la demande', rtrim(config('app.frontend_url'), '/').'/pro/demandes')
            ->line('Rappel : Par respect pour la santé mentale des étudiants, merci de confirmer ou décliner cette demande sous 24h.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'appointment_requested',
            'appointment_id' => $this->appointment->id,
            'scheduled_at' => $this->appointment->scheduled_at?->toIso8601String(),
            'message' => 'Nouvelle demande de rendez-vous en attente de votre confirmation.',
        ];
    }
}
