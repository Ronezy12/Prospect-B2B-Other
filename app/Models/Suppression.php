<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Suppression extends Model
{
    protected $table = 'pm_suppressions';

    protected $fillable = ['email_hash', 'scope', 'reason', 'origin', 'note', 'created_by'];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
