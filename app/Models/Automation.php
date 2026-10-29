<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Automation extends Model
{
    use SoftDeletes;

    protected $table = 'pm_automations';

    protected $attributes = ['status' => 'brouillon'];

    protected $fillable = ['name', 'trigger', 'max_runs_per_subject', 'frequency_days'];

    public function versions(): HasMany
    {
        return $this->hasMany(AutomationVersion::class)->orderByDesc('version');
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(AutomationVersion::class)->ofMany('version', 'max');
    }

    public function activeVersion(): BelongsTo
    {
        return $this->belongsTo(AutomationVersion::class, 'active_version_id');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(AutomationRun::class);
    }

    public function addVersion(array $definition, ?int $userId): AutomationVersion
    {
        $next = $this->current_version + 1;
        $version = $this->versions()->create(['version' => $next, 'definition' => $definition, 'created_by' => $userId]);
        $this->forceFill(['current_version' => $next])->save();

        return $version;
    }

    public function statusLabel(): string
    {
        return ['brouillon' => 'Brouillon', 'active' => 'Active', 'en_pause' => 'En pause', 'arretee' => 'Arrêtée'][$this->status] ?? $this->status;
    }

    public function triggerLabel(): string
    {
        return config('prospection.automation.triggers')[$this->trigger] ?? $this->trigger;
    }
}
