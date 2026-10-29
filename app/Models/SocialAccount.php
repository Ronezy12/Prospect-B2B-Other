<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Connexion officielle Meta : identifiants chiffrés en base, jamais renvoyés au navigateur. */
class SocialAccount extends Model
{
    protected $table = 'pm_social_accounts';

    protected $fillable = ['name', 'is_active'];

    protected $hidden = ['settings', 'webhook_token'];

    protected function casts(): array
    {
        return [
            'settings' => 'encrypted:array',
            'is_active' => 'boolean',
            'last_tested_at' => 'datetime',
            'last_event_at' => 'datetime',
        ];
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return ($this->settings ?? [])[$key] ?? $default;
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(SocialConversation::class, 'account_id');
    }
}
