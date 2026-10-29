<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/** Paramètres globaux du module, modifiables depuis l'interface. */
class Setting extends Model
{
    protected $table = 'pm_settings';

    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'json'];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = Cache::remember('pm_setting_'.$key, 300, fn () => static::where('key', $key)->first()?->value);

        return $value ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('pm_setting_'.$key);
    }
}
