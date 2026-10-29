<?php

namespace App\Services\Simple;

use App\Models\Company;
use App\Models\Opportunity;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\Task;
use App\Models\User;
use App\Services\ActivityRecorder;
use App\Services\AuditLogger;
use App\Services\Automation\AutomationEngine;
use App\Services\PipelineService;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * « Que s'est-il passé ? » en un clic : l'outil fait le reste.
 *  - note dans l'historique ;
 *  - statut du prospect et étape de son affaire (créée si besoin) ;
 *  - rappel du jour marqué comme fait, rappel suivant créé (hors week-end).
 */
class QuickOutcomeService
{
    public function __construct(
        private readonly PipelineService $pipelines,
        private readonly ActivityRecorder $activities,
        private readonly AuditLogger $audit,
    ) {}

    /** @return string message de confirmation lisible */
    public function apply(Company $company, string $key, User $user, ?string $note = null, ?CarbonInterface $date = null): string
    {
        $outcome = config('prospection.quick_outcomes.'.$key);
        $done = [];

        $nextTask = DB::transaction(function () use ($company, $key, $outcome, $user, $note, $date, &$done) {
            $this->activities->record($company, $outcome['activity'], trim($outcome['text'].' '.($note ? '— '.$note : '')), ['resultat' => $key], $user->id);

            // Statut du prospect.
            if ($outcome['status'] && $company->status !== $outcome['status'] && ! $company->isClient()) {
                $before = $company->status;
                $company->forceFill(['status' => $outcome['status']])->save();
                $done[] = 'statut « '.$company->statusLabel().' »';
                DB::afterCommit(fn () => app(AutomationEngine::class)->fire('company.status_changed', $company, 'status:'.$before.'>'.$company->status.':'.now()->timestamp, ['avant' => $before, 'après' => $company->status]));
            }

            // Affaire : étape mise à jour, créée si le résultat en annonce une.
            if ($outcome['stage']) {
                $opportunity = $company->opportunities()->whereNull('closed_at')->latest('id')->first();
                if (! $opportunity && ! in_array($outcome['stage'], ['lost'], true)) {
                    $opportunity = $this->createOpportunity($company, $user);
                }
                if ($opportunity && ($stage = $this->stage($opportunity->pipeline, $outcome['stage']))) {
                    if ($stage->type === 'lost') {
                        $opportunity->lost_reason = $note ? mb_substr($note, 0, 190) : 'Pas intéressé';
                    }
                    if ($this->pipelines->move($opportunity, $stage, null, $user)) {
                        $done[] = 'affaire passée à « '.$stage->name.' »';
                    }
                }
            }

            // Rappels : ceux du jour sont faits ; tout est annulé si l'affaire est terminée.
            $open = Task::where('company_id', $company->id)->where('status', 'a_faire');
            if ($outcome['next'] === null) {
                $count = (clone $open)->update(['status' => 'annule', 'completed_at' => now(), 'completed_by' => $user->id]);
                if ($count) {
                    $done[] = $count.' rappel(s) clôturé(s)';
                }
            } else {
                (clone $open)->where('due_at', '<=', now()->endOfDay())->update(['status' => 'fait', 'completed_at' => now(), 'completed_by' => $user->id]);
            }

            if (! $outcome['next']) {
                return null;
            }
            [$title, $type, $days] = $outcome['next'];
            $due = $date ? Carbon::parse($date)->setTime(9, 0) : $this->workday(now()->addDays($days)->setTime(9, 0));
            $task = new Task(['title' => mb_substr($title.' — '.$company->name, 0, 190), 'type' => $type, 'priority' => $key === 'rdv' ? 'haute' : 'normale', 'due_at' => $due, 'description' => $note]);
            $task->company_id = $company->id;
            $task->opportunity_id = $company->opportunities()->whereNull('closed_at')->latest('id')->value('id');
            $task->assignee_id = $company->owner_id ?? $user->id;
            $task->created_by = $user->id;
            $task->save();

            return $task;
        });

        if ($key === 'gagne' && $user->can('prospects.convert') && ! $company->isClient()) {
            $company->forceFill(['lifecycle' => 'client', 'converted_at' => now(), 'converted_by' => $user->id])->save();
            $this->activities->record($company, 'systeme', 'Prospect converti en client.', [], $user->id);
            $this->audit->log('company.converted', $company, ['origine' => 'bouton Signé'], $user->id);
            $done[] = 'converti en client';
        }
        $this->audit->log('company.quick_outcome', $company, ['résultat' => $key], $user->id);

        if ($nextTask) {
            $done[] = 'prochain rappel « '.$nextTask->title.' » le '.$nextTask->due_at->locale('fr')->isoFormat('dddd D MMMM');
        }

        return 'C\'est noté : '.config('prospection.quick_outcomes.'.$key.'.label').($done ? ' — '.implode(', ', $done).'.' : '.');
    }

    private function createOpportunity(Company $company, User $user): ?Opportunity
    {
        $sector = $company->sector?->parent ?? $company->sector;
        $pipeline = $sector ? $this->pipelines->ensureForSector($sector) : Pipeline::where('is_active', true)->orderBy('id')->first();
        $stage = $pipeline?->firstOpenStage();
        if (! $pipeline || ! $stage) {
            return null;
        }
        $opportunity = new Opportunity(['company_id' => $company->id, 'title' => mb_substr('Affaire — '.$company->name, 0, 190), 'priority' => 'normale']);
        $opportunity->owner_id = $company->owner_id ?? $user->id;

        return $this->pipelines->create($opportunity, $pipeline, $stage, $user);
    }

    private function stage(Pipeline $pipeline, string $wanted): ?PipelineStage
    {
        if (in_array($wanted, ['won', 'lost'], true)) {
            return $pipeline->stages()->where('type', $wanted)->first();
        }

        return $pipeline->stages()->where('name', $wanted)->first();
    }

    /** Un rappel ne tombe jamais un samedi ou un dimanche. */
    private function workday(Carbon $date): Carbon
    {
        while ($date->isWeekend()) {
            $date->addDay();
        }

        return $date;
    }
}
