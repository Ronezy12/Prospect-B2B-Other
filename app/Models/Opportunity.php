<?php

namespace App\Models;

use App\Models\Concerns\HasOwnerScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Opportunity extends Model
{
    use HasOwnerScope, SoftDeletes;

    protected $table = 'pm_opportunities';

    protected $fillable = ['company_id', 'contact_id', 'title', 'priority', 'amount', 'next_action', 'follow_up_at', 'lost_reason'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'follow_up_at' => 'date',
            'closed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'stage_id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(StageHistory::class)->latest('changed_at')->latest('id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function isOverdue(): bool
    {
        return ! $this->closed_at && $this->follow_up_at && $this->follow_up_at->lt(today());
    }

    /** Exclut les opportunités dont l'entreprise a été supprimée (archivée). */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereHas('company');
    }

    /** @param array<string, mixed> $filters */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['q'] ?? null, function (Builder $q, $term) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
                $q->where(fn (Builder $w) => $w->where('title', 'like', $like)
                    ->orWhereHas('company', fn ($c) => $c->where('name', 'like', $like)));
            })
            ->when($filters['pipeline_id'] ?? null, fn (Builder $q, $v) => $q->where('pipeline_id', $v))
            ->when($filters['stage_id'] ?? null, fn (Builder $q, $v) => $q->where('stage_id', $v))
            ->when($filters['owner_id'] ?? null, fn (Builder $q, $v) => $v === 'none' ? $q->whereNull('owner_id') : $q->where('owner_id', $v))
            ->when($filters['priority'] ?? null, fn (Builder $q, $v) => $q->where('priority', $v))
            ->when(($filters['state'] ?? null) === 'open', fn (Builder $q) => $q->whereNull('closed_at'))
            ->when(($filters['state'] ?? null) === 'closed', fn (Builder $q) => $q->whereNotNull('closed_at'))
            ->when(($filters['state'] ?? null) === 'overdue', fn (Builder $q) => $q->whereNull('closed_at')->whereDate('follow_up_at', '<', today()));
    }

    public function priorityLabel(): string
    {
        return config('prospection.priorities')[$this->priority] ?? $this->priority;
    }
}
