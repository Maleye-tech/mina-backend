<?php

namespace Modules\Appointments\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProfessionalInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim(config('app.frontend_url'), '/').'/reset-password?'.http_build_query([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        return (new MailMessage)
            ->subject('Mina Care : Invitation à rejoindre l’espace professionnel')
            ->greeting("Bonjour {$notifiable->name},")
            ->line('Un administrateur Mina Care vous invite à rejoindre l’espace professionnel.')
            ->line('Cliquez sur le bouton ci-dessous pour définir votre mot de passe. Ce lien est valable pendant 60 minutes.')
            ->action('Définir mon mot de passe', $url)
            ->line('Si vous n’attendiez pas cette invitation, vous pouvez ignorer cet e-mail.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'professional_invitation',
            'message' => 'Votre invitation à rejoindre Mina Care a été envoyée par e-mail.',
        ];
    }
}
