<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AutomationRun extends Model
{
    protected $table = 'pm_automation_runs';

    protected $fillable = ['automation_id', 'automation_version_id', 'subject_type', 'subject_id', 'trigger_key', 'status', 'current_step', 'resume_at', 'context', 'is_test'];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'is_test' => 'boolean',
            'resume_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public const STATUS_LABELS = [
        'en_attente' => 'En attente (délai)',
        'en_cours' => 'En cours',
        'termine' => 'Terminée',
        'arrete' => 'Arrêtée',
        'erreur' => 'Erreur',
    ];

    public function automation(): BelongsTo
    {
        return $this->belongsTo(Automation::class)->withTrashed();
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(AutomationVersion::class, 'automation_version_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function logs(): HasMany
    {
        return $this->hasMany(AutomationLog::class, 'run_id')->orderBy('id');
    }
}
