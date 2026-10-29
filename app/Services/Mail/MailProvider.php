<?php

namespace App\Services\Mail;

interface MailProvider
{
    public function send(OutgoingMessage $message): SendResult;

    /** Nom court du fournisseur, enregistré avec chaque événement. */
    public function name(): string;

    /** Le fournisseur sait-il remonter livraisons, ouvertures, rebonds (webhooks) ? */
    public function reportsEvents(): bool;
}
