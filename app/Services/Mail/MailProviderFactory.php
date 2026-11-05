<?php

namespace App\Services\Mail;

use App\Models\MailAccount;
use InvalidArgumentException;

class MailProviderFactory
{
    /** @var array<int, MailProvider> */
    private array $instances = [];

    /** @var array<int, string|null> configuration utilisée par chaque instance (null = remplacée par un test) */
    private array $versions = [];

    public function for(MailAccount $account): MailProvider
    {
        // Le worker d'envoi tourne en continu : si le compte a été modifié depuis (nouveau mot de passe…),
        // la connexion est recréée avec la nouvelle configuration.
        $version = (string) $account->updated_at?->getTimestamp();
        if (isset($this->instances[$account->id]) && $this->versions[$account->id] !== null && $this->versions[$account->id] !== $version) {
            unset($this->instances[$account->id]);
        }
        if (! isset($this->instances[$account->id])) {
            $this->versions[$account->id] = $version;
            $this->instances[$account->id] = match ($account->driver) {
                'smtp' => new SmtpProvider($account),
                'brevo' => new BrevoProvider($account),
                'log' => new LogProvider,
                default => throw new InvalidArgumentException("Fournisseur inconnu : {$account->driver}"),
            };
        }

        return $this->instances[$account->id];
    }

    /** Permet aux tests de remplacer un fournisseur. */
    public function fake(MailAccount $account, MailProvider $provider): void
    {
        $this->instances[$account->id] = $provider;
        $this->versions[$account->id] = null;
    }

    public function forget(MailAccount $account): void
    {
        unset($this->instances[$account->id], $this->versions[$account->id]);
    }
}
