<?php

namespace App\Services\Mail;

use App\Models\MailAccount;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * API transactionnelle Brevo (v3). Les identifiants restent côté serveur.
 * Documentation : https://developers.brevo.com/reference/sendtransacemail
 */
class BrevoProvider implements MailProvider
{
    public const ENDPOINT = 'https://api.brevo.com/v3/smtp/email';

    public function __construct(private readonly MailAccount $account) {}

    public function name(): string
    {
        return 'brevo';
    }

    public function reportsEvents(): bool
    {
        return true;
    }

    public function send(OutgoingMessage $message): SendResult
    {
        $key = (string) $this->account->setting('api_key');
        if ($key === '') {
            return SendResult::permanent('Clé API Brevo non configurée.');
        }

        $payload = array_filter([
            'sender' => ['name' => $message->fromName, 'email' => $message->fromEmail],
            'to' => [array_filter(['email' => $message->toEmail, 'name' => $message->toName])],
            'replyTo' => $message->replyTo ? ['email' => $message->replyTo] : null,
            'subject' => $message->subject,
            'htmlContent' => $message->html,
            'textContent' => $message->text,
            // X-Mailin-custom est renvoyé par Brevo dans ses webhooks : il relie l'événement au destinataire.
            'headers' => $message->headers + ['X-Mailin-custom' => $message->messageKey],
        ]);

        try {
            $response = Http::withHeaders(['api-key' => $key, 'accept' => 'application/json'])
                ->timeout(20)
                ->post(self::ENDPOINT, $payload);
        } catch (ConnectionException) {
            return SendResult::temporary('API Brevo injoignable.');
        } catch (Throwable $e) {
            report($e);

            return SendResult::temporary('Erreur technique lors de l\'appel à l\'API Brevo.');
        }

        if ($response->successful()) {
            return SendResult::accepted($response->json('messageId'));
        }

        $error = 'Brevo '.$response->status().' : '.mb_substr((string) ($response->json('message') ?? 'erreur'), 0, 200);

        return $response->status() === 429 || $response->serverError()
            ? SendResult::temporary($error)
            : SendResult::permanent($error);
    }
}
