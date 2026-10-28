<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Contact;
use App\Support\Normalizer;

/**
 * Détection des doublons, par ordre de fiabilité :
 * 1. SIREN identique ;
 * 2. domaine du site web identique ;
 * 3. raison sociale normalisée + code postal identiques.
 * Les fiches supprimées logiquement sont incluses pour ne pas recréer
 * silencieusement une entreprise écartée.
 */
class DuplicateFinder
{
    /** @param array<string, mixed> $data */
    public function findCompany(array $data, ?int $ignoreId = null): ?Company
    {
        $query = fn () => Company::withTrashed()->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId));

        if ($siren = Normalizer::siren($data['registration_number'] ?? null)) {
            if ($match = $query()->where('siren', $siren)->first()) {
                return $match;
            }
        }

        if ($domain = Normalizer::domain($data['website'] ?? null)) {
            if ($match = $query()->where('domain', $domain)->first()) {
                return $match;
            }
        }

        $name = Normalizer::companyName($data['name'] ?? null);
        $postal = trim((string) ($data['postal_code'] ?? ''));
        if ($name && $postal !== '') {
            return $query()->where('name_normalized', $name)->where('postal_code', $postal)->first();
        }

        return null;
    }

    public function findContactByEmail(?string $email, ?int $ignoreId = null): ?Contact
    {
        $email = Normalizer::email($email);
        if (! $email) {
            return null;
        }

        return Contact::withTrashed()
            ->where('email_normalized', $email)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->first();
    }
}
