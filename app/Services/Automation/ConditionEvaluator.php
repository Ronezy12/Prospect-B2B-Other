<?php

namespace App\Services\Automation;

use App\Models\AutomationRun;
use App\Models\CampaignRecipient;
use App\Models\Company;
use App\Models\Opportunity;
use App\Services\SuppressionService;

class ConditionEvaluator
{
    public function __construct(private readonly SuppressionService $suppressions) {}

    public function evaluate(array $step, AutomationRun $run, ?Company $company, ?Opportunity $opportunity): bool
    {
        $value = $this->value($step['field'], $run, $company, $opportunity);
        $expected = $step['value'] ?? null;

        return match ($step['operator']) {
            'equals' => (string) $value === (string) $expected,
            'not_equals' => (string) $value !== (string) $expected,
            'is_true' => (bool) $value,
            'is_false' => ! $value,
            default => false,
        };
    }

    public function value(string $field, AutomationRun $run, ?Company $company, ?Opportunity $opportunity): mixed
    {
        $contactIds = $company?->contacts()->pluck('id') ?? collect();

        return match ($field) {
            'company.status' => $company?->status,
            'company.lifecycle' => $company?->lifecycle,
            'company.sector_id' => $company?->sector_id,
            'company.has_owner' => (bool) $company?->owner_id,
            'company.has_open_opportunity' => $company ? $company->opportunities()->whereNull('closed_at')->exists() : false,
            'company.replied_since_start' => CampaignRecipient::whereIn('contact_id', $contactIds)->where('replied_at', '>=', $run->started_at)->exists(),
            'company.opposed' => $company ? (bool) $this->suppressions->blockingReasons($company->contacts()->pluck('email')->filter()->all(), 'prospection') : false,
            'opportunity.stage_type' => $opportunity?->stage?->type,
            default => null,
        };
    }
}
