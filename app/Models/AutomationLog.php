<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutomationLog extends Model
{
    protected $table = 'pm_automation_logs';

    public const UPDATED_AT = null;

    protected $fillable = ['run_id', 'step_id', 'type', 'result', 'message'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
