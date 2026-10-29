<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SocialConversation extends Model
{
    protected $table = 'pm_social_conversations';

    protected $fillable = ['account_id', 'channel', 'participant_id', 'participant_label', 'status'];

    protected function casts(): array
    {
        return ['last_inbound_at' => 'datetime', 'last_message_at' => 'datetime', 'unread' => 'boolean'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class, 'account_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(SocialProfile::class, 'profile_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SocialMessage::class, 'conversation_id')->orderBy('sent_at')->orderBy('id');
    }

    /**
     * Messagerie partagée de la Page : une conversation non rattachée est visible de tous
     * les utilisateurs habilités ; une conversation rattachée suit le périmètre de la fiche.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->can('prospects.view_all')) {
            return $query;
        }

        return $query->where(fn (Builder $q) => $q->whereNull('company_id')
            ->orWhereIn('company_id', Company::query()->visibleTo($user)->select('id')));
    }

    public function isVisibleTo(User $user): bool
    {
        return static::query()->visibleTo($user)->whereKey($this->getKey())->exists();
    }

    /** Réponse autorisée uniquement dans la fenêtre suivant le dernier message de la personne. */
    public function canReply(): bool
    {
        return $this->last_inbound_at !== null
            && $this->last_inbound_at->gt(now()->subHours((int) config('prospection.social.meta.reply_window_hours')));
    }

    public function replyDeadline(): ?CarbonInterface
    {
        return $this->last_inbound_at?->copy()->addHours((int) config('prospection.social.meta.reply_window_hours'));
    }

    public function channelLabel(): string
    {
        return config('prospection.social.channels')[$this->channel] ?? $this->channel;
    }
}
