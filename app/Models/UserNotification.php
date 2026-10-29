<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserNotification extends Model
{
    protected $table = 'pm_notifications';

    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'title', 'body', 'url', 'read_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime', 'created_at' => 'datetime'];
    }

    public static function notify(?int $userId, string $title, ?string $body = null, ?string $url = null): ?self
    {
        return $userId ? static::create(['user_id' => $userId, 'title' => mb_substr($title, 0, 190), 'body' => $body, 'url' => $url]) : null;
    }
}
