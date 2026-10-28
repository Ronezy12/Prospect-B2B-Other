<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Activity extends Model
{
    protected $table = 'pm_activities';

    protected $fillable = ['subject_type', 'subject_id', 'user_id', 'type', 'body', 'meta', 'occurred_at'];

    protected function casts(): array
    {
        return ['meta' => 'array', 'occurred_at' => 'datetime'];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
