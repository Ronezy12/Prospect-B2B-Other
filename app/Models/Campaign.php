<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Campaign extends Model
{
    use SoftDeletes;

    protected $table = 'pm_campaigns';

    protected $attributes = ['status' => 'brouillon'];

    protected $fillable = ['name', 'type', 'template_id', 'mail_account_id', 'segment_id', 'list_id', 'exclusions', 'from_name', 'reply_to', 'scheduled_at'];

    protected function casts(): array
    {
        return [
            'audience' => 'array',
            'exclusions' => 'array',
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'last_test_at' => 'datetime',
        ];
    }

    public const EDITABLE = ['brouillon'];

    public const ACTIVE = ['programmee', 'en_cours', 'en_pause'];

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class)->withTrashed();
    }

    public function templateVersion(): BelongsTo
    {
        return $this->belongsTo(TemplateVersion::class);
    }

    public function mailAccount(): BelongsTo
    {
        return $this->belongsTo(MailAccount::class);
    }

    public function segment(): BelongsTo
    {
        return $this->belongsTo(Segment::class)->withTrashed();
    }

    public function list(): BelongsTo
    {
        return $this->belongsTo(NewsletterList::class, 'list_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, self::EDITABLE, true);
    }

    /** Envois soumis au lien d'opposition obligatoire (prospection, y compris individuelle, et newsletter). */
    public function isMarketing(): bool
    {
        return in_array($this->type, ['prospection', 'newsletter', 'individuel'], true);
    }

    public function statusLabel(): string
    {
        return config('prospection.campaign_statuses')[$this->status] ?? $this->status;
    }

    public function typeLabel(): string
    {
        return config('prospection.campaign_types')[$this->type] ?? $this->type;
    }

    /** Périmètre de la liste d'opposition à consulter selon la finalité. */
    public function suppressionScope(): string
    {
        return match ($this->type) {
            'newsletter' => 'newsletter',
            'operationnel' => 'operationnel',
            default => 'prospection',
        };
    }
}
