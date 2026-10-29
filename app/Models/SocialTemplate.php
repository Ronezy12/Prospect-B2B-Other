<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SocialTemplate extends Model
{
    protected $table = 'pm_social_templates';

    protected $fillable = ['name', 'platform', 'kind', 'body', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** Modèles actifs utilisables pour une plateforme (ou valables pour toutes). */
    public function scopeFor(Builder $query, string $platform): Builder
    {
        return $query->where('is_active', true)->where(fn (Builder $q) => $q->whereNull('platform')->orWhere('platform', $platform));
    }

    public function kindLabel(): string
    {
        return config('prospection.social.template_kinds')[$this->kind] ?? $this->kind;
    }

    public function lengthLimit(): ?int
    {
        return config('prospection.social.length_limits')[$this->kind] ?? null;
    }
}
