<?php

namespace App\Services\Mail;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/** Mode test : le message est écrit dans le journal, rien n'est envoyé. */
class LogProvider implements MailProvider
{
    public function name(): string
    {
        return 'log';
    }

    public function reportsEvents(): bool
    {
        return false;
    }

    public function send(OutgoingMessage $message): SendResult
    {
        Log::channel(config('logging.default'))->info('[Prospection] E-mail non envoyé (journal local)', [
            'to' => $message->toEmail,
            'subject' => $message->subject,
            'from' => $message->fromEmail,
            'key' => $message->messageKey,
        ]);

        return SendResult::accepted('log-'.Str::uuid());
    }
}
