<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SocialProfile extends Model
{
    protected $table = 'pm_social_profiles';

    protected $fillable = ['platform', 'url', 'status', 'next_action_at', 'contact_id'];

    protected $attributes = ['status' => 'a_contacter'];

    protected function casts(): array
    {
        return ['next_action_at' => 'datetime', 'last_interaction_at' => 'datetime'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(SocialInteraction::class, 'profile_id')->latest('occurred_at')->latest('id');
    }

    /** Périmètre : celui de l'entreprise (tout / équipe / mes fiches). */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereHas('company', fn (Builder $q) => $q->visibleTo($user));
    }

    public function isVisibleTo(User $user): bool
    {
        return static::query()->visibleTo($user)->whereKey($this->getKey())->exists();
    }

    public function platformLabel(): string
    {
        return config('prospection.social.platforms.'.$this->platform.'.label', $this->platform);
    }

    public function statusLabel(): string
    {
        return config('prospection.social.statuses')[$this->status] ?? $this->status;
    }

    public function isOpposed(): bool
    {
        return $this->status === 'ne_plus_contacter';
    }

    public function isClosed(): bool
    {
        return in_array($this->status, config('prospection.social.closed_statuses'), true);
    }
}
