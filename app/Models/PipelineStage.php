<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PipelineStage extends Model
{
    protected $table = 'pm_pipeline_stages';

    protected $fillable = ['pipeline_id', 'name', 'position', 'type', 'probability'];

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class, 'stage_id');
    }

    public function isClosing(): bool
    {
        return in_array($this->type, ['won', 'lost'], true);
    }
}
