<?php

namespace App\Services\Mail;

/**
 * Remplace les variables {{prenom}}… (valeurs échappées), ajoute le pied de
 * page légal des envois marketing et produit la version texte.
 */
class TemplateRenderer
{
    /**
     * @param  array<string, string|null>  $data
     * @return array{subject: string, html: string, text: string}
     */
    public function render(string $subject, string $html, ?string $preheader, array $data, bool $marketing, ?string $senderLine = null): array
    {
        $unsubscribe = $data['lien_desinscription'] ?? null;

        $subject = $this->replace($subject, $data, false);
        $body = $this->replace($html, $data, true);

        if ($preheader) {
            $hidden = '<div style="display:none;max-height:0;overflow:hidden;opacity:0">'.e($this->replace($preheader, $data, false)).'</div>';
            $body = preg_match('/<body[^>]*>/i', $body) ? preg_replace('/(<body[^>]*>)/i', '$1'.$hidden, $body, 1) : $hidden.$body;
        }

        // Le lien de désinscription est obligatoire en prospection et newsletter :
        // s'il n'a pas été placé dans le modèle, un pied de page est ajouté.
        if ($marketing && $unsubscribe && ! str_contains($body, e($unsubscribe)) && ! str_contains($body, $unsubscribe)) {
            $footer = '<div style="margin-top:32px;padding-top:16px;border-top:1px solid #e5e5e5;font:12px/1.5 Arial,sans-serif;color:#777">'
                .($senderLine ? e($senderLine).'<br>' : '')
                .'Vous ne souhaitez plus recevoir nos e-mails ? <a href="'.e($unsubscribe).'" style="color:#777">Se désinscrire</a>.</div>';
            $body = stripos($body, '</body>') !== false ? preg_replace('#</body>#i', $footer.'</body>', $body, 1) : $body.$footer;
        }

        return ['subject' => trim(preg_replace('/\s+/', ' ', strip_tags($subject))), 'html' => $body, 'text' => $this->toText($body)];
    }

    /** @param array<string, string|null> $data */
    private function replace(string $content, array $data, bool $escape): string
    {
        return preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/i', function ($m) use ($data, $escape) {
            $value = (string) ($data[strtolower($m[1])] ?? '');

            return $escape ? e($value) : $value;
        }, $content);
    }

    public function toText(string $html): string
    {
        $text = preg_replace('#<(style|script|head)[^>]*>.*?</\1>#is', '', $html);
        $text = preg_replace('#<div style="display:none[^>]*>.*?</div>#is', '', $text);
        $text = preg_replace_callback('#<a\s[^>]*href="([^"]+)"[^>]*>(.*?)</a>#is', fn ($m) => trim(strip_tags($m[2])).' ('.html_entity_decode($m[1]).')', $text);
        $text = preg_replace('#<br\s*/?>#i', "\n", $text);
        $text = preg_replace('#</(p|div|h[1-6]|li|tr|table)>#i', "\n\n", $text);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+/", ' ', $text);
        $text = preg_replace("/\n\s*\n\s*\n+/", "\n\n", $text);

        return trim(implode("\n", array_map('trim', explode("\n", $text))));
    }

    /** Données d'exemple pour l'aperçu et les e-mails de test. */
    public static function sampleData(string $sender): array
    {
        return [
            'prenom' => 'Claire',
            'nom' => 'Martin',
            'civilite' => 'Mme',
            'fonction' => 'Directrice d\'agence',
            'entreprise' => 'Agence Exemple',
            'ville' => 'Lyon',
            'expediteur' => $sender,
            'lien_desinscription' => '#desinscription-exemple',
        ];
    }
}
