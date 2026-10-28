<?php

namespace App\Services;

use App\Models\Suppression;
use App\Support\Normalizer;
use Illuminate\Support\Facades\Auth;

/**
 * Point de contrôle unique de la liste d'opposition. Tout envoi, import ou
 * scénario doit interroger ce service avant d'agir sur une adresse.
 */
class SuppressionService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function hash(string $email): string
    {
        $key = config('prospection.suppression_key') ?: config('app.key');

        return hash_hmac('sha256', Normalizer::email($email), (string) $key);
    }

    /**
     * Une opposition « all » bloque tous les périmètres marketing.
     */
    public function isSuppressed(?string $email, string $scope = 'all'): bool
    {
        if (! Normalizer::email($email)) {
            return false;
        }

        return Suppression::where('email_hash', $this->hash($email))
            ->whereIn('scope', array_unique(['all', $scope]))
            ->exists();
    }

    /**
     * @param  array<int, string>  $emails
     * @return array<string, true> adresses normalisées opposées
     */
    public function suppressedAmong(array $emails, string $scope = 'all'): array
    {
        $byHash = [];
        foreach ($emails as $email) {
            if ($normalized = Normalizer::email($email)) {
                $byHash[$this->hash($normalized)] = $normalized;
            }
        }
        if (! $byHash) {
            return [];
        }

        $found = [];
        foreach (array_chunk(array_keys($byHash), 500) as $chunk) {
            Suppression::whereIn('email_hash', $chunk)
                ->whereIn('scope', array_unique(['all', $scope]))
                ->pluck('email_hash')
                ->each(function ($hash) use (&$found, $byHash) {
                    $found[$byHash[$hash]] = true;
                });
        }

        return $found;
    }

    public function suppress(string $email, string $scope, string $reason, string $origin, ?string $note = null): Suppression
    {
        $suppression = Suppression::firstOrCreate(
            ['email_hash' => $this->hash($email), 'scope' => $scope],
            ['reason' => $reason, 'origin' => $origin, 'note' => $note, 'created_by' => Auth::id()],
        );

        if ($suppression->wasRecentlyCreated) {
            $this->audit->log('suppression.created', $suppression, ['scope' => $scope, 'reason' => $reason, 'origin' => $origin]);
        }

        return $suppression;
    }
}
