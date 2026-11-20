<?php

namespace Modules\Auth\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminLoginCode extends Notification
{
    public function __construct(public readonly string $code) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Votre code de connexion')
            ->line("Votre code de connexion est : {$this->code}")
            ->line('Ce code expire dans 10 minutes.')
            ->line('Si vous n’êtes pas à l’origine de cette demande, ignorez cet e-mail.');
    }
}
