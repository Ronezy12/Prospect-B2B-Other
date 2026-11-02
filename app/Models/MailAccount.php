<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailAccount extends Model
{
    protected $table = 'pm_mail_accounts';

    protected $fillable = ['name', 'driver', 'from_email', 'from_name', 'reply_to', 'signature', 'hourly_limit', 'daily_limit', 'is_active'];

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

    /** Signature en HTML sûr : texte échappé, retours à la ligne, liens web et adresses cliquables. */
    public function signatureHtml(): ?string
    {
        $text = trim((string) $this->signature);
        if ($text === '') {
            return null;
        }
        $html = nl2br(e($text), false);
        $html = preg_replace('#\b(https?://[^\s<]+)#i', '<a href="$1" style="color:#0a3f94">$1</a>', $html);
        $html = preg_replace('#(?<![/\w.@])([a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,})#i', '<a href="mailto:$1" style="color:#0a3f94">$1</a>', $html);

        return '<div style="margin-top:20px;font:14px/1.5 Arial,Helvetica,sans-serif;color:#374151">'.$html.'</div>';
    }

    public function driverLabel(): string
    {
        return config('prospection.mail_drivers')[$this->driver] ?? $this->driver;
    }
}
