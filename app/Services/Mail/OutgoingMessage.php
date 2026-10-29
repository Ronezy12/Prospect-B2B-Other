<?php

namespace App\Services\Mail;

final class OutgoingMessage
{
    /** @param array<string, string> $headers */
    public function __construct(
        public readonly string $toEmail,
        public readonly ?string $toName,
        public readonly string $fromEmail,
        public readonly string $fromName,
        public readonly ?string $replyTo,
        public readonly string $subject,
        public readonly string $html,
        public readonly string $text,
        public readonly string $messageKey,
        public readonly array $headers = [],
    ) {}
}
