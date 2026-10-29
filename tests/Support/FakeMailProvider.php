<?php

namespace Tests\Support;

use App\Services\Mail\MailProvider;
use App\Services\Mail\OutgoingMessage;
use App\Services\Mail\SendResult;

/** Fournisseur de test : enregistre les messages et rejoue des résultats programmés. */
class FakeMailProvider implements MailProvider
{
    /** @var array<int, OutgoingMessage> */
    public array $sent = [];

    /** @param array<int, SendResult> $results */
    public function __construct(public array $results = []) {}

    public function send(OutgoingMessage $message): SendResult
    {
        $this->sent[] = $message;

        return array_shift($this->results) ?? SendResult::accepted('fake-'.count($this->sent));
    }

    public function name(): string
    {
        return 'fake';
    }

    public function reportsEvents(): bool
    {
        return false;
    }

    /** @return array<int, string> */
    public function recipients(): array
    {
        return array_map(fn ($m) => $m->toEmail, $this->sent);
    }
}
