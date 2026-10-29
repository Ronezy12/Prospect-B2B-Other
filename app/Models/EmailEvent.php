<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailEvent extends Model
{
    protected $table = 'pm_email_events';

    public const UPDATED_AT = null;

    protected $fillable = ['recipient_id', 'campaign_id', 'provider', 'provider_event_id', 'type', 'payload', 'occurred_at'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'occurred_at' => 'datetime'];
    }
}
