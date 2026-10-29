<?php

namespace App\Services\Mail;

use App\Models\MailAccount;
use InvalidArgumentException;

class MailProviderFactory
{
    /** @var array<int, MailProvider> */
    private array $instances = [];

    public function for(MailAccount $account): MailProvider
    {
        return $this->instances[$account->id] ??= match ($account->driver) {
            'smtp' => new SmtpProvider($account),
            'brevo' => new BrevoProvider($account),
            'log' => new LogProvider,
            default => throw new InvalidArgumentException("Fournisseur inconnu : {$account->driver}"),
        };
    }

    /** Permet aux tests de remplacer un fournisseur. */
    public function fake(MailAccount $account, MailProvider $provider): void
    {
        $this->instances[$account->id] = $provider;
    }

    public function forget(MailAccount $account): void
    {
        unset($this->instances[$account->id]);
    }
}
