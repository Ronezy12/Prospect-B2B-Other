<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrivacyRequest extends Model
{
    protected $table = 'pm_privacy_requests';

    protected $fillable = ['type', 'requester_label', 'channel', 'received_at', 'due_at', 'note'];

    public const TYPES = [
        'acces' => 'Accès (savoir ce que nous avons)',
        'portabilite' => 'Portabilité (recevoir ses données)',
        'rectification' => 'Rectification',
        'effacement' => 'Effacement',
        'opposition' => 'Opposition (ne plus être contacté)',
        'levee_opposition' => 'Levée d\'une opposition',
    ];

    public const STATUSES = ['ouverte' => 'À traiter', 'traitee' => 'Traitée', 'refusee' => 'Refusée (motivée)'];

    protected function casts(): array
    {
        return ['received_at' => 'date', 'due_at' => 'date', 'handled_at' => 'datetime'];
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function isLate(): bool
    {
        return $this->status === 'ouverte' && $this->due_at->isPast();
    }
}
