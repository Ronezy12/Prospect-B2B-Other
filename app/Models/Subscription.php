<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    protected $table = 'pm_subscriptions';

    protected $fillable = ['list_id', 'contact_id', 'status', 'consent_source', 'consented_at', 'unsubscribed_at', 'created_by'];

    protected function casts(): array
    {
        return ['consented_at' => 'datetime', 'unsubscribed_at' => 'datetime'];
    }

    public function list(): BelongsTo
    {
        return $this->belongsTo(NewsletterList::class, 'list_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
