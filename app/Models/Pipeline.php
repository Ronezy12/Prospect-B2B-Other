<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pipeline extends Model
{
    protected $table = 'pm_pipelines';

    protected $fillable = ['sector_id', 'name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    public function stages(): HasMany
    {
        return $this->hasMany(PipelineStage::class)->orderBy('position')->orderBy('id');
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class);
    }

    /** Crée les étapes par défaut d'une pipeline vide. */
    public function seedDefaultStages(): void
    {
        if ($this->stages()->exists()) {
            return;
        }
        foreach (config('prospection.default_stages') as $position => [$name, $type, $probability]) {
            $this->stages()->create(compact('name', 'type', 'probability', 'position'));
        }
    }

    public function firstOpenStage(): ?PipelineStage
    {
        return $this->stages()->where('type', 'open')->first();
    }
}
