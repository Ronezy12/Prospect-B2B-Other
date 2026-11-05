<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Nettoyage d'un fragment HTML fourni par l'utilisateur (signature d'e-mail) :
 * liste blanche de balises et d'attributs, liens http(s)/mailto/tel uniquement,
 * images https ou intégrées, aucun script, formulaire, cadre ni gestionnaire d'événement.
 */
final class HtmlSanitizer
{
    private const ALLOWED = ['a', 'b', 'br', 'center', 'div', 'em', 'font', 'h1', 'h2', 'h3', 'h4', 'hr', 'i', 'img', 'li', 'ol', 'p', 'small', 'span', 'strong', 'sub', 'sup', 'table', 'tbody', 'td', 'tfoot', 'th', 'thead', 'tr', 'u', 'ul'];

    /** Balises supprimées avec leur contenu. */
    private const DROPPED = ['script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'form', 'input', 'button', 'textarea', 'select', 'option', 'link', 'meta', 'base', 'svg', 'math', 'template', 'noscript', 'title', 'head', 'video', 'audio', 'source', 'canvas'];

    private const ATTRIBUTES = ['style', 'align', 'valign', 'width', 'height', 'border', 'cellpadding', 'cellspacing', 'bgcolor', 'color', 'face', 'size', 'dir', 'title', 'colspan', 'rowspan', 'role'];

    public static function clean(string $html, int $maxLength = 60000): string
    {
        $html = mb_substr(trim($html), 0, $maxLength);
        if ($html === '') {
            return '';
        }
        // Document complet collé (fichier .html) : seul le contenu du <body> est gardé.
        if (preg_match('#<body[^>]*>(.*)</body>#is', $html, $m)) {
            $html = $m[1];
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?><div id="pm-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $dom->getElementById('pm-root');
        if (! $root) {
            return '';
        }
        self::walk($root);

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $dom->saveHTML($child);
        }

        return trim($out);
    }

    private static function walk(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child->nodeType === XML_COMMENT_NODE || $child->nodeType === XML_PI_NODE || $child->nodeType === XML_CDATA_SECTION_NODE) {
                $node->removeChild($child);

                continue;
            }
            if (! $child instanceof DOMElement) {
                continue;
            }
            $tag = strtolower($child->tagName);
            if (in_array($tag, self::DROPPED, true)) {
                $node->removeChild($child);

                continue;
            }
            if (! in_array($tag, self::ALLOWED, true)) {
                // Balise inconnue : on garde son contenu, pas la balise.
                self::walk($child);
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);

                continue;
            }
            self::cleanAttributes($child, $tag);
            if ($tag === 'img' && ! $child->hasAttribute('src')) {
                $node->removeChild($child);

                continue;
            }
            self::walk($child);
        }
    }

    private static function cleanAttributes(DOMElement $el, string $tag): void
    {
        foreach (iterator_to_array($el->attributes) as $attr) {
            $name = strtolower($attr->name);
            $value = trim($attr->value);
            $keep = in_array($name, self::ATTRIBUTES, true)
                || ($tag === 'a' && in_array($name, ['href', 'target'], true))
                || ($tag === 'img' && in_array($name, ['src', 'alt'], true));

            if ($keep && $name === 'href' && ! preg_match('#^(https?://|mailto:|tel:)#i', $value)) {
                $keep = false;
            }
            if ($keep && $name === 'src' && ! preg_match('#^(https://|data:image/(png|jpe?g|gif|webp);base64,)#i', $value)) {
                $keep = false;
            }
            if ($keep && $name === 'target') {
                $value = '_blank';
            }
            if ($keep && $name === 'style') {
                $value = self::cleanStyle($value);
                $keep = $value !== '';
            }

            $el->removeAttribute($attr->name);
            if ($keep) {
                $el->setAttribute($name, $value);
            }
        }
        if ($tag === 'a' && $el->hasAttribute('href')) {
            $el->setAttribute('rel', 'noopener');
        }
    }

    /** Styles en ligne conservés, sauf ce qui peut charger du contenu ou exécuter du code. */
    private static function cleanStyle(string $style): string
    {
        $decoded = html_entity_decode($style, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (preg_match('/expression\s*\(|javascript:|vbscript:|@import|behavior\s*:|-moz-binding|url\s*\(|\\\\/i', $decoded)) {
            // Déclarations dangereuses retirées une à une ; les autres restent.
            $decoded = implode(';', array_filter(explode(';', $decoded), fn ($d) => ! preg_match('/expression\s*\(|javascript:|vbscript:|@import|behavior\s*:|-moz-binding|url\s*\(|\\\\/i', $d)));
        }

        return mb_substr(trim($decoded, " ;\t\n\r"), 0, 2000);
    }
}
