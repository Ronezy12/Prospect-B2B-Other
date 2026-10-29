<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Template extends Model
{
    use SoftDeletes;

    protected $table = 'pm_templates';

    protected $fillable = ['name', 'category'];

    public function versions(): HasMany
    {
        return $this->hasMany(TemplateVersion::class)->orderByDesc('version');
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(TemplateVersion::class)->ofMany('version', 'max');
    }

    /** Enregistre une nouvelle version immuable (aucune version existante n'est modifiée). */
    public function addVersion(array $content, ?int $userId): TemplateVersion
    {
        $next = $this->current_version + 1;
        $version = $this->versions()->create($content + ['version' => $next, 'created_by' => $userId]);
        $this->forceFill(['current_version' => $next])->save();

        return $version;
    }
}
