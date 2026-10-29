<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemplateVersion extends Model
{
    protected $table = 'pm_template_versions';

    public const UPDATED_AT = null;

    protected $fillable = ['version', 'subject', 'preheader', 'html', 'design', 'created_by'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class)->withTrashed();
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
