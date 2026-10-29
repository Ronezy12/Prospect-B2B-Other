<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailAccount extends Model
{
    protected $table = 'pm_mail_accounts';

    protected $fillable = ['name', 'driver', 'from_email', 'from_name', 'reply_to', 'hourly_limit', 'daily_limit', 'is_active'];

    /** Les secrets ne sortent jamais du serveur : ni dans les vues, ni en JSON. */
    protected $hidden = ['settings'];

    protected function casts(): array
    {
        return [
            'settings' => 'encrypted:array',
            'is_active' => 'boolean',
            'last_tested_at' => 'datetime',
        ];
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return ($this->settings ?? [])[$key] ?? $default;
    }

    public function hasSecret(string $key): bool
    {
        return filled($this->setting($key));
    }

    public function driverLabel(): string
    {
        return config('prospection.mail_drivers')[$this->driver] ?? $this->driver;
    }
}
