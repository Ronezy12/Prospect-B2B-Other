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

    public function render(): string
    {
        $inner = self::$cache[$this->name] ??= $this->load();

        return '<svg class="icon '.e($this->class).'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'.$inner.'</svg>';
    }

    private function load(): string
    {
        $path = resource_path('icons/'.basename($this->name).'.svg');
        if (! is_file($path)) {
            return '';
        }
        preg_match('#<svg[^>]*>(.*)</svg>#s', file_get_contents($path), $m);

        return trim(preg_replace('/\s+/', ' ', $m[1] ?? ''));
    }
}
