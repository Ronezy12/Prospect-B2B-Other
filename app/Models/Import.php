<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Import extends Model
{
    protected $table = 'pm_imports';

    protected $fillable = [
        'user_id', 'original_name', 'stored_path', 'file_type', 'file_hash', 'headers', 'mapping', 'options',
        'status', 'total_rows', 'processed_rows', 'created_count', 'updated_count', 'skipped_count',
        'error_count', 'failure_message', 'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'headers' => 'array',
            'mapping' => 'array',
            'options' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public const STATUS_LABELS = [
        'uploaded' => 'Téléversé',
        'mapped' => 'Correspondance définie',
        'queued' => 'En file d\'attente',
        'processing' => 'En cours',
        'completed' => 'Terminé',
        'failed' => 'Échec',
        'cancelled' => 'Annulé',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rows(): HasMany
    {
        return $this->hasMany(ImportRow::class);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function isFinished(): bool
    {
        return in_array($this->status, ['completed', 'failed', 'cancelled'], true);
    }
}
