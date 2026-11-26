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
        $isStudent = $notifiable->id === $this->appointment->student_id;
        $professional = $this->appointment->professionalUser ?? $this->appointment->professional?->user;
        $counterpart = $isStudent
            ? ($professional?->name ?? 'Votre professionnel')
            : ($this->appointment->student?->pseudonym ?? 'un étudiant');
        $time = $this->appointment->scheduled_at->format('d/m/Y à H:i');
        $path = $isStudent
            ? '/espace/accompagnement/'.$this->appointment->id
            : '/pro/demandes';

        $mail = (new MailMessage)
            ->subject('Rappel de votre rendez-vous Mina Care')
            ->greeting("Bonjour {$notifiable->name},")
            ->line("Ceci est un rappel pour votre consultation prévue le {$time} avec {$counterpart}.");

        if ($link = $this->appointment->meeting_url ?: $this->appointment->meeting_link) {
            $mail->line("Lien de la consultation vidéo : {$link}");
        }

        return $mail
            ->action('Voir mon rendez-vous', rtrim(config('app.frontend_url'), '/').$path)
            ->line('Prenez soin de vous.');
    }

    public function toArray(object $notifiable): array
    {
        $isStudent = $notifiable->id === $this->appointment->student_id;
        $professional = $this->appointment->professionalUser ?? $this->appointment->professional?->user;

        return [
            'type' => 'appointment_reminder',
            'appointment_id' => $this->appointment->id,
            'scheduled_at' => $this->appointment->scheduled_at->toIso8601String(),
            'counterpart' => $isStudent
                ? $professional?->name
                : ($this->appointment->student?->pseudonym ?? 'un étudiant'),
            'meeting_url' => $this->appointment->meeting_url ?: $this->appointment->meeting_link,
            'message' => 'Rappel : votre consultation a lieu le '.$this->appointment->scheduled_at->format('d/m/Y à H:i').'.',
        ];
    }
}
