<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Tag extends Model
{
    protected $table = 'pm_tags';

    protected $fillable = ['name', 'color'];

    public function companies(): MorphToMany
    {
        return $this->morphedByMany(Company::class, 'taggable', 'pm_taggables');
    }

    /** @return array<int, int> */
    public static function idsFromString(?string $value): array
    {
        $names = collect(preg_split('/[,;|]/', (string) $value))
            ->map(fn ($name) => trim(mb_substr($name, 0, 80)))
            ->filter()
            ->unique(fn ($name) => mb_strtolower($name));

        return $names->map(fn ($name) => static::firstOrCreate(['name' => $name])->id)->values()->all();
    }
}
