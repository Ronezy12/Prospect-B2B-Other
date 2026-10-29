<?php

namespace App\Models;

use App\Models\Concerns\HasOwnerScope;
use App\Support\Normalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends Model
{
    use HasOwnerScope, SoftDeletes;

    protected $table = 'pm_contacts';

    protected $fillable = [
        'company_id', 'civility', 'first_name', 'last_name', 'job_title', 'email', 'phone', 'mobile',
        'linkedin_url', 'audience_type', 'legal_basis', 'source_id', 'collected_at', 'collection_note',
        'is_primary', 'owner_id', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'collected_at' => 'date',
            'is_primary' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Contact $contact) {
            $contact->email = Normalizer::email($contact->email);
            $contact->email_normalized = $contact->email;
            $contact->phone = Normalizer::phone($contact->phone);
            $contact->mobile = Normalizer::phone($contact->mobile);
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject')->latest('occurred_at')->latest('id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function fullName(): string
    {
        $name = trim($this->first_name.' '.$this->last_name);

        return $name !== '' ? $name : ($this->email ?? 'Contact #'.$this->id);
    }

    /** @param array<string, mixed> $filters */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['q'] ?? null, function (Builder $q, $term) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
                $q->where(fn (Builder $w) => $w->where('last_name', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhere('email_normalized', 'like', mb_strtolower($like))
                    ->orWhere('job_title', 'like', $like)
                    ->orWhereHas('company', fn ($c) => $c->where('name', 'like', $like)));
            })
            ->when($filters['audience_type'] ?? null, fn (Builder $q, $v) => $q->where('audience_type', $v))
            ->when($filters['legal_basis'] ?? null, fn (Builder $q, $v) => $q->where('legal_basis', $v))
            ->when($filters['owner_id'] ?? null, fn (Builder $q, $v) => $q->where('owner_id', $v))
            ->when($filters['sector_id'] ?? null, fn (Builder $q, $v) => $q->whereHas('company', fn ($c) => $c->where(fn ($w) => $w->where('sector_id', $v)->orWhere('subsector_id', $v))));
    }
}
