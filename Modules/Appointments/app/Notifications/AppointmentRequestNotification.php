<?php

namespace Modules\Appointments\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Appointments\Models\Appointment;

class AppointmentRequestNotification extends Notification
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
        $student = $this->appointment->student;
        $proName = $this->appointment->professional?->display_name ?? $this->appointment->professional?->user?->name ?? 'Votre professionnel';
        $studentPseudonym = $student->pseudonym ?? 'Un étudiant';
        $time = $this->appointment->scheduled_at->format('d/m/Y à H:i');

        return (new MailMessage)
            ->subject('Nouvelle demande de rendez-vous — Mina Care')
            ->greeting("Bonjour {$proName},")
            ->line("{$studentPseudonym} vous a demandé un rendez-vous le {$time}.")
            ->line('Motif : '.($this->appointment->student_notes ?: 'Aucun motif précisé.'))
            ->action('Voir la demande', url('/care/appointments/'.$this->appointment->id))
            ->line('Prenez soin de vous.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'appointment_request',
            'appointment_id' => $this->appointment->id,
            'student_pseudonym' => $this->appointment->student?->pseudonym ?: 'Étudiant',
            'scheduled_at' => $this->appointment->scheduled_at->toIso8601String(),
            'message' => 'Nouvelle demande de rendez-vous.',
        ];
    }
}
