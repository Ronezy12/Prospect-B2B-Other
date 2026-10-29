<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationVersion extends Model
{
    protected $table = 'pm_automation_versions';

    public const UPDATED_AT = null;

    protected $fillable = ['version', 'definition', 'created_by'];

    protected function casts(): array
    {
        return ['definition' => 'array', 'created_at' => 'datetime'];
    }

    public function automation(): BelongsTo
    {
        return $this->belongsTo(Automation::class)->withTrashed();
    }

    /** @return array<int, array<string, mixed>> */
    public function steps(): array
    {
        return (array) ($this->definition['steps'] ?? []);
    }
}
