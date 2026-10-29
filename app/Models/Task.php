<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use SoftDeletes;

    protected $table = 'pm_tasks';

    protected $fillable = ['title', 'description', 'type', 'priority', 'due_at'];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * Périmètre : ses tâches (assignées ou créées), celles de son équipe pour un
     * responsable, toutes avec le périmètre global.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->can('prospects.view_all')) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($user) {
            $q->where('assignee_id', $user->id)->orWhere('created_by', $user->id);
            if ($user->can('prospects.view_team') && $user->team_id) {
                $q->orWhereIn('assignee_id', User::where('team_id', $user->team_id)->select('id'));
            }
        });
    }

    public function isVisibleTo(User $user): bool
    {
        return static::query()->visibleTo($user)->whereKey($this->getKey())->exists();
    }

    public function isOpen(): bool
    {
        return $this->status === 'a_faire';
    }

    public function isOverdue(): bool
    {
        return $this->isOpen() && $this->due_at->isPast();
    }
}
