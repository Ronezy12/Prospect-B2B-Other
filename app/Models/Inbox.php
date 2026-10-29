<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inbox extends Model
{
    protected $table = 'pm_inboxes';

    protected $fillable = ['name', 'folder', 'is_active'];

    protected $hidden = ['settings'];

    protected function casts(): array
    {
        return ['settings' => 'encrypted:array', 'is_active' => 'boolean', 'last_checked_at' => 'datetime'];
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return ($this->settings ?? [])[$key] ?? $default;
    }
}
