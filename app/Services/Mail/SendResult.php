<?php

namespace App\Services\Mail;

/**
 * Résultat d'une demande d'envoi. « accepted » signifie que le serveur ou
 * l'API a pris le message en charge : ce n'est PAS une preuve de livraison.
 */
final class SendResult
{
    private function __construct(
        public readonly string $status,
        public readonly ?string $messageId = null,
        public readonly ?string $error = null,
    ) {}

    public static function accepted(?string $messageId): self
    {
        return new self('accepted', $messageId);
    }

    /** Erreur passagère : nouvelle tentative différée. */
    public static function temporary(string $error): self
    {
        return new self('temporary', null, mb_substr($error, 0, 255));
    }

    /** Refus définitif : aucune nouvelle tentative. */
    public static function permanent(string $error): self
    {
        return new self('permanent', null, mb_substr($error, 0, 255));
    }

    public function isAccepted(): bool
    {
        return $this->status === 'accepted';
    }
}
