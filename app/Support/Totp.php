<?php

namespace App\Support;

/**
 * Codes à usage unique basés sur le temps (RFC 6238, compatibles Google Authenticator,
 * Microsoft Authenticator, 1Password…). 6 chiffres, pas de 30 secondes, HMAC-SHA1.
 */
final class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function generateSecret(): string
    {
        $bytes = random_bytes(20);
        $bits = '';
        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }
        $secret = '';
        foreach (str_split($bits, 5) as $chunk) {
            $secret .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }

        return $secret;
    }

    public static function code(string $secret, int $timestep): string
    {
        $key = self::base32Decode($secret);
        $hash = hash_hmac('sha1', pack('N*', 0).pack('N*', $timestep), $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24) | (ord($hash[$offset + 1]) << 16) | (ord($hash[$offset + 2]) << 8) | ord($hash[$offset + 3]);

        return str_pad((string) ($value % 1_000_000), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Vérifie un code (tolérance d'un pas de 30 s avant/après).
     *
     * @return int|null le pas de temps reconnu (pour refuser sa réutilisation), sinon null
     */
    public static function verify(string $secret, string $code, ?int $now = null): ?int
    {
        $code = preg_replace('/\D/', '', $code);
        if (strlen($code) !== 6) {
            return null;
        }
        $current = intdiv($now ?? time(), 30);
        foreach ([0, -1, 1] as $drift) {
            if (hash_equals(self::code($secret, $current + $drift), $code)) {
                return $current + $drift;
            }
        }

        return null;
    }

    public static function uri(string $secret, string $account, string $issuer): string
    {
        return 'otpauth://totp/'.rawurlencode($issuer.':'.$account).'?'.http_build_query(['secret' => $secret, 'issuer' => $issuer, 'digits' => 6, 'period' => 30]);
    }

    private static function base32Decode(string $secret): string
    {
        $bits = '';
        foreach (str_split(strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $secret))) as $char) {
            $bits .= str_pad(decbin(strpos(self::ALPHABET, $char)), 5, '0', STR_PAD_LEFT);
        }
        $bytes = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $bytes .= chr(bindec($chunk));
            }
        }

        return $bytes;
    }
}
