<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StageHistory extends Model
{
    protected $table = 'pm_stage_history';

    public $timestamps = false;

    protected $fillable = ['opportunity_id', 'from_stage_id', 'to_stage_id', 'from_name', 'to_name', 'user_id', 'changed_at'];

    protected function casts(): array
    {
        return ['changed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
