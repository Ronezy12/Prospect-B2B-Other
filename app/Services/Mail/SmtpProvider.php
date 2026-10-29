<?php

namespace App\Services\Mail;

use App\Models\MailAccount;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Throwable;

class SmtpProvider implements MailProvider
{
    private ?TransportInterface $transport = null;

    public function __construct(private readonly MailAccount $account) {}

    public function name(): string
    {
        return 'smtp';
    }

    public function reportsEvents(): bool
    {
        return false;
    }

    public function send(OutgoingMessage $message): SendResult
    {
        $email = (new Email)
            ->from(new Address($message->fromEmail, $message->fromName))
            ->to(new Address($message->toEmail, (string) $message->toName))
            ->subject($message->subject)
            ->html($message->html)
            ->text($message->text);
        if ($message->replyTo) {
            $email->replyTo($message->replyTo);
        }
        $domain = substr(strrchr($message->fromEmail, '@') ?: '@localhost', 1);
        $email->getHeaders()->addIdHeader('Message-ID', $message->messageKey.'@'.$domain);
        foreach ($message->headers as $name => $value) {
            $email->getHeaders()->addTextHeader($name, $value);
        }

        try {
            $sent = $this->transport()->send($email);

            return SendResult::accepted($sent?->getMessageId() ?? $message->messageKey.'@'.$domain);
        } catch (TransportExceptionInterface $e) {
            // Codes SMTP 5xx : refus définitif ; 4xx et erreurs réseau : passagères.
            return preg_match('/\b5\d\d\b/', $e->getMessage()) && ! preg_match('/\b4\d\d\b/', $e->getMessage())
                ? SendResult::permanent($this->clean($e))
                : SendResult::temporary($this->clean($e));
        } catch (Throwable $e) {
            report($e);

            return SendResult::temporary('Erreur technique lors de l\'envoi SMTP.');
        }
    }

    private function transport(): TransportInterface
    {
        if ($this->transport) {
            return $this->transport;
        }
        $scheme = $this->account->setting('encryption') === 'ssl' ? 'smtps' : 'smtp';
        $auth = filled($this->account->setting('username'))
            ? rawurlencode((string) $this->account->setting('username')).':'.rawurlencode((string) $this->account->setting('password')).'@'
            : '';
        $dsn = sprintf('%s://%s%s:%d', $scheme, $auth, $this->account->setting('host'), (int) $this->account->setting('port', 587));

        return $this->transport = Transport::fromDsn($dsn);
    }

    /** Message d'erreur sans identifiants. */
    private function clean(Throwable $e): string
    {
        $text = $e->getMessage();
        foreach (['password', 'username'] as $key) {
            if ($secret = (string) $this->account->setting($key)) {
                $text = str_replace([$secret, rawurlencode($secret)], '***', $text);
            }
        }

        return $text;
    }
}
