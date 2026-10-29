<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialInteraction extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'pm_social_interactions';

    protected $fillable = ['type', 'body', 'occurred_at'];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(SocialProfile::class, 'profile_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function typeLabel(): string
    {
        return config('prospection.social.interaction_types')[$this->type] ?? $this->type;
    }
}
