<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignRecipient extends Model
{
    protected $table = 'pm_campaign_recipients';

    protected $fillable = ['campaign_id', 'contact_id', 'email', 'merge_data', 'status', 'exclusion_reason', 'idempotency_key'];

    protected function casts(): array
    {
        return [
            'merge_data' => 'array',
            'next_attempt_at' => 'datetime',
            'locked_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class)->withTrashed();
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class)->withTrashed();
    }

    public static function keyFor(int $campaignId, string $email): string
    {
        return hash('sha256', $campaignId.'|'.mb_strtolower($email));
    }
}
