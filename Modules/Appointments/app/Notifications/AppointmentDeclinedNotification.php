<?php

namespace Modules\Appointments\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Appointments\Models\Appointment;

class AppointmentDeclinedNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly Appointment $appointment) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Mina Care : Information concernant votre demande de rendez-vous')
            ->greeting("Bonjour {$notifiable->name},")
            ->line('Le praticien n’est malheureusement pas disponible sur le créneau demandé.')
            ->line("Nous vous invitons à choisir un autre créneau disponible ou à contacter un autre professionnel de l'équipe Mina Care.")
            ->action('Choisir un autre créneau', rtrim(config('app.frontend_url'), '/').'/espace/accompagnement')
            ->line("Si vous ressentez une détresse immédiate, utilisez le bouton d'urgence disponible sur votre application.");
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'appointment_declined',
            'appointment_id' => $this->appointment->id,
            'message' => 'Le professionnel n’a pas pu valider votre demande. Veuillez choisir un autre créneau.',
        ];
    }
}
