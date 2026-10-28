<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Périmètre de données : tout / mon équipe / mes fiches.
 * Appliqué côté serveur à toutes les requêtes de liste, d'export et d'action en masse.
 */
trait HasOwnerScope
{
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->can('prospects.view_all')) {
            return $query;
        }

        if ($user->can('prospects.view_team') && $user->team_id) {
            return $query->where(function (Builder $q) use ($user) {
                $q->whereIn($this->qualifyColumn('owner_id'), User::where('team_id', $user->team_id)->select('id'))
                    ->orWhereNull($this->qualifyColumn('owner_id'));
            });
        }

        return $query->where($this->qualifyColumn('owner_id'), $user->id);
    }

    public function isVisibleTo(User $user): bool
    {
        return static::query()->visibleTo($user)->whereKey($this->getKey())->exists();
    }
}
