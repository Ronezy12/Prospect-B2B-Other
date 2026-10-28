<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Normalisations utilisées pour la détection des doublons et la liste
 * d'opposition. Toute comparaison d'identité passe par ces fonctions.
 */
final class Normalizer
{
    public static function email(?string $email): ?string
    {
        $email = trim((string) $email);
        if ($email === '') {
            return null;
        }

        return mb_strtolower($email);
    }

    public static function companyName(?string $name): ?string
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        $name = Str::lower(Str::ascii($name));
        $name = str_replace('.', '', $name); // « S.A.R.L. » → « sarl »
        $name = preg_replace('/[^a-z0-9]+/', ' ', $name);
        // Formes juridiques usuelles ignorées pour comparer les raisons sociales.
        $name = preg_replace('/\b(sarl|sas|sasu|sa|eurl|sci|snc|selarl|ei|eirl|scp|gie)\b/', ' ', $name);

        return trim(preg_replace('/\s+/', ' ', $name)) ?: null;
    }

    /** SIREN (9 chiffres) ou SIRET (14 chiffres), sinon null. */
    public static function registrationNumber(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        return in_array(strlen($digits), [9, 14], true) ? $digits : null;
    }

    public static function siren(?string $value): ?string
    {
        $number = self::registrationNumber($value);

        return $number ? substr($number, 0, 9) : null;
    }

    public static function phone(?string $phone): ?string
    {
        $phone = trim((string) $phone);
        if ($phone === '') {
            return null;
        }
        $clean = preg_replace('/[^\d+]/', '', $phone);
        if (preg_match('/^0\d{9}$/', $clean)) {
            return '+33'.substr($clean, 1);
        }
        if (str_starts_with($clean, '0033')) {
            return '+'.substr($clean, 2);
        }

        return $clean ?: null;
    }

    /** Affichage lisible : +33 4 10 20 30 40. */
    public static function displayPhone(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }
        if (preg_match('/^\+33(\d)(\d{2})(\d{2})(\d{2})(\d{2})$/', $phone, $m)) {
            return "+33 {$m[1]} {$m[2]} {$m[3]} {$m[4]} {$m[5]}";
        }

        return $phone;
    }

    public static function domain(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }
        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }
        $host = parse_url($url, PHP_URL_HOST);
        if (! $host || ! preg_match('/^([a-z0-9-]+\.)+[a-z]{2,}$/i', $host)) {
            return null;
        }

        return preg_replace('/^www\./', '', mb_strtolower($host));
    }

    public static function website(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        return preg_match('#^https?://#i', $url) ? $url : 'https://'.$url;
    }
}
