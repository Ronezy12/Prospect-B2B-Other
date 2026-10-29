<?php

namespace App\Models;

use App\Models\Concerns\HasOwnerScope;
use App\Support\Normalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasOwnerScope, SoftDeletes;

    protected $table = 'pm_companies';

    protected $fillable = [
        'name', 'registration_number', 'sector_id', 'subsector_id', 'website', 'email', 'phone',
        'address', 'postal_code', 'city', 'country', 'source_id', 'acquired_at', 'status', 'owner_id', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'acquired_at' => 'date',
            'converted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Company $company) {
            $company->name_normalized = Normalizer::companyName($company->name);
            $company->registration_number = Normalizer::registrationNumber($company->registration_number);
            $company->siren = Normalizer::siren($company->registration_number);
            $company->website = Normalizer::website($company->website);
            $company->domain = Normalizer::domain($company->website);
            $company->phone = Normalizer::phone($company->phone);
            $company->email = Normalizer::email($company->email);
        });
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    public function subsector(): BelongsTo
    {
        return $this->belongsTo(Sector::class, 'subsector_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable', 'pm_taggables');
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject')->latest('occurred_at')->latest('id');
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function socialProfiles(): HasMany
    {
        return $this->hasMany(SocialProfile::class)->orderBy('platform')->orderBy('id');
    }

    public function scopeProspects(Builder $query): Builder
    {
        return $query->where('lifecycle', 'prospect');
    }

    /** @param array<string, mixed> $filters */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['q'] ?? null, function (Builder $q, $term) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
                $siren = Normalizer::siren($term);
                $q->where(function (Builder $w) use ($like, $siren) {
                    $w->where('name', 'like', $like)
                        ->orWhere('city', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->when($siren, fn (Builder $s) => $s->orWhere('siren', $siren))
                        ->orWhereHas('contacts', fn (Builder $c) => $c->where(fn (Builder $n) => $n
                            ->where('email_normalized', 'like', mb_strtolower($like))
                            ->orWhere('last_name', 'like', $like)));
                });
            })
            ->when($filters['lifecycle'] ?? null, fn (Builder $q, $v) => $q->where('lifecycle', $v))
            ->when($filters['sector_id'] ?? null, fn (Builder $q, $v) => $q->where(fn ($w) => $w->where('sector_id', $v)->orWhere('subsector_id', $v)))
            ->when($filters['status'] ?? null, fn (Builder $q, $v) => $q->where('status', $v))
            ->when($filters['source_id'] ?? null, fn (Builder $q, $v) => $q->where('source_id', $v))
            ->when($filters['owner_id'] ?? null, fn (Builder $q, $v) => $v === 'none' ? $q->whereNull('owner_id') : $q->where('owner_id', $v))
            ->when($filters['city'] ?? null, fn (Builder $q, $v) => $q->where('city', 'like', str_replace(['%', '_'], ['\%', '\_'], $v).'%'))
            ->when($filters['postal_code'] ?? null, fn (Builder $q, $v) => $q->where('postal_code', 'like', preg_replace('/\D/', '', $v).'%'))
            ->when($filters['tag_id'] ?? null, fn (Builder $q, $v) => $q->whereHas('tags', fn ($t) => $t->whereKey($v)));
    }

    public function statusLabel(): string
    {
        return config('prospection.company_statuses')[$this->status] ?? $this->status;
    }

    public function isClient(): bool
    {
        return $this->lifecycle === 'client';
    }
}
