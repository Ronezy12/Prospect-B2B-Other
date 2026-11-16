<?php

namespace App\View\Components;

use Illuminate\View\Component;

/**
 * Icônes Lucide (licence ISC) intégrées en SVG : aucune requête externe,
 * compatible avec la politique de sécurité de contenu.
 */
class Icon extends Component
{
    /** @var array<string, string> */
    private static array $cache = [];

    public function __construct(public string $name, public string $class = '') {}

    /** @var array<string, bool> icônes pleines (logos) plutôt qu'au trait */
    private static array $filled = [];

    public function render(): string
    {
        $inner = self::$cache[$this->name] ??= $this->load();
        if (self::$filled[$this->name] ?? false) {
            return '<svg class="icon '.e($this->class).'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false">'.$inner.'</svg>';
        }

        return '<svg class="icon '.e($this->class).'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'.$inner.'</svg>';
    }

    private function load(): string
    {
        $path = resource_path('icons/'.basename($this->name).'.svg');
        if (! is_file($path)) {
            return '';
        }
        $content = file_get_contents($path);
        self::$filled[$this->name] = str_contains($content, 'data-filled="true"');
        preg_match('#<svg[^>]*>(.*)</svg>#s', $content, $m);

        return trim(preg_replace('/\s+/', ' ', $m[1] ?? ''));
    }
}
