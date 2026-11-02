<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class Campaign extends Model
{
    use SoftDeletes;

    protected $table = 'pm_campaigns';

    protected $attributes = ['status' => 'brouillon'];

    protected $fillable = ['name', 'type', 'template_id', 'mail_account_id', 'account_ids', 'segment_id', 'list_id', 'exclusions', 'from_name', 'reply_to', 'scheduled_at', 'track_opens', 'track_clicks'];

    protected function casts(): array
    {
        return [
            'audience' => 'array',
            'exclusions' => 'array',
            'account_ids' => 'array',
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'last_test_at' => 'datetime',
            'track_opens' => 'boolean',
            'track_clicks' => 'boolean',
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

    /**
     * Comptes utilisés pour l'envoi : le compte principal puis les comptes supplémentaires
     * (envoi réparti). Seuls les comptes actifs sont retenus.
     *
     * @return Collection<int, MailAccount>
     */
    public function sendingAccounts(): Collection
    {
        $ids = array_values(array_unique(array_filter(array_merge([$this->mail_account_id], array_map('intval', (array) $this->account_ids)))));
        if (! $ids) {
            return collect();
        }
        $accounts = MailAccount::whereIn('id', $ids)->where('is_active', true)->get()->keyBy('id');

        return collect($ids)->map(fn ($id) => $accounts[$id] ?? null)->filter()->values();
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
