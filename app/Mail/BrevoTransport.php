<?php

namespace App\Mail;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class BrevoTransport extends AbstractTransport
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $endpoint = 'https://api.brevo.com/v3/smtp/email',
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $email = $message->getOriginalMessage();

        if (! $email instanceof Email) {
            throw new RuntimeException('Brevo ne peut envoyer que des messages Symfony Email.');
        }

        $sender = $this->addresses($email->getFrom())[0] ?? null;

        if (! $sender || ! filter_var($sender['email'], FILTER_VALIDATE_EMAIL) || $sender['email'] === 'hello@example.com') {
            throw new RuntimeException(
                "Erreur de configuration du serveur : la variable BREVO_FROM_EMAIL (ou MAIL_FROM_ADDRESS) n'est pas configurée avec une adresse email valide sur Render."
            );
        }

        if (trim($this->apiKey) === '') {
            throw new RuntimeException(
                "Erreur de configuration du serveur : la variable BREVO_API_KEY n'est pas configurée sur Render."
            );
        }

        $payload = [
            'sender' => $sender,
            'to' => $this->addresses($email->getTo()),
            'subject' => (string) $email->getSubject(),
            'htmlContent' => $this->body($email->getHtmlBody()),
            'textContent' => $this->body($email->getTextBody()),
        ];

        if ($cc = $this->addresses($email->getCc())) {
            $payload['cc'] = $cc;
        }

        if ($bcc = $this->addresses($email->getBcc())) {
            $payload['bcc'] = $bcc;
        }

        if ($replyTo = $this->addresses($email->getReplyTo())) {
            $payload['replyTo'] = $replyTo[0];
        }

        if ($payload['textContent'] === null) {
            unset($payload['textContent']);
        }

        if ($payload['htmlContent'] === null && ! isset($payload['textContent'])) {
            throw new RuntimeException('Brevo: le message ne contient ni HTML ni texte.');
        }

        try {
            Http::withHeaders([
                'api-key' => $this->apiKey,
                'Accept' => 'application/json',
            ])->post($this->endpoint, $payload)->throw();
        } catch (RequestException $e) {
            $responseBody = $e->response?->json() ?? [];
            $errorMessage = $responseBody['message'] ?? $e->getMessage();

            throw new RuntimeException(
                "Brevo a rejeté l'envoi de l'e-mail ({$errorMessage}). Assurez-vous que l'adresse '{$sender['email']}' est bien enregistrée et vérifiée comme expéditeur dans votre compte Brevo (Menu Expéditeurs & IP), et que votre clé BREVO_API_KEY est valide.",
                previous: $e
            );
        }
    }

    /**
     * @param  array<int, Address>  $addresses
     * @return array<int, array{email: string, name: string}>
     */
    private function addresses(array $addresses): array
    {
        return array_values(array_map(
            static fn (Address $address): array => [
                'email' => $address->getAddress(),
                'name' => $address->getName() !== '' ? $address->getName() : $address->getAddress(),
            ],
            $addresses,
        ));
    }

    private function body(mixed $body): ?string
    {
        if ($body === null) {
            return null;
        }

        if (is_resource($body)) {
            $body = stream_get_contents($body);
        }

        $body = (string) $body;

        return $body === '' ? null : $body;
    }

    public function __toString(): string
    {
        return 'brevo';
    }
}
