<?php

namespace App\Services\Automation;

use App\Models\AutomationLog;
use App\Models\AutomationRun;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\UserNotification;
use Throwable;

/**
 * Exécute un scénario pas à pas. Garanties :
 *  - une exécution n'est traitée que par un worker à la fois (réservation atomique) ;
 *  - une action déjà réalisée n'est jamais rejouée (journal consulté avant chaque action),
 *    ce qui rend la reprise sûre après une erreur ou un redémarrage ;
 *  - les délais sont persistés (resume_at) et repris par le planificateur ;
 *  - une boucle infinie est interrompue (nombre d'étapes par passage limité) ;
 *  - scénario en pause : les exécutions attendent ; arrêté : elles s'arrêtent.
 */
class AutomationRunner
{
    public function __construct(
        private readonly ConditionEvaluator $conditions,
        private readonly ActionExecutor $actions,
    ) {}

    public function process(AutomationRun $run): void
    {
        $claimed = AutomationRun::whereKey($run->id)->where('status', 'en_attente')
            ->where(fn ($q) => $q->whereNull('resume_at')->orWhere('resume_at', '<=', now()))
            ->update(['status' => 'en_cours', 'updated_at' => now()]);
        if (! $claimed) {
            return;
        }
        $run->refresh();
        $automation = $run->automation;

        if (! $run->is_test && $automation->status === 'en_pause') {
            $run->forceFill(['status' => 'en_attente', 'resume_at' => now()->addMinutes(15)])->save();

            return;
        }
        if (! $run->is_test && in_array($automation->status, ['arretee', 'brouillon'], true) || $automation->trashed()) {
            $this->finish($run, 'arrete', 'Scénario arrêté.');

            return;
        }

        try {
            $this->advance($run);
        } catch (Throwable $e) {
            report($e);
            $attempts = $run->attempts + 1;
            $this->log($run, $run->current_step, 'erreur', 'erreur', 'Erreur technique : nouvelle tentative prévue.');
            if ($attempts >= (int) config('prospection.automation.max_attempts')) {
                $this->finish($run, 'erreur', 'Échec après plusieurs tentatives.');
                UserNotification::notify($automation->created_by, "Échec du scénario « {$automation->name} »", 'Une exécution a échoué après plusieurs tentatives. Consultez son journal.', route('pm.automations.show', $automation, false));
            } else {
                $run->forceFill(['status' => 'en_attente', 'attempts' => $attempts, 'resume_at' => now()->addMinutes(5 * $attempts)])->save();
            }
        }
    }

    private function advance(AutomationRun $run): void
    {
        $steps = collect($run->version->steps())->values();
        $index = $steps->keyBy('id');
        [$company, $opportunity] = $this->resolve($run);
        $context = (array) $run->context;

        $stepId = $run->current_step ?? $steps->first()['id'] ?? 'end';
        $budget = (int) config('prospection.automation.max_steps_per_execution');

        while ($stepId !== 'end' && $stepId !== null) {
            if ($budget-- <= 0) {
                $this->finish($run, 'erreur', 'Trop d\'étapes enchaînées : vérifiez qu\'aucune condition ne boucle.');

                return;
            }
            $step = $index[$stepId] ?? null;
            if (! $step) {
                $this->finish($run, 'erreur', "Étape {$stepId} introuvable.");

                return;
            }
            $position = $steps->search(fn ($s) => $s['id'] === $stepId);
            $next = $step['next'] ?? ($steps[$position + 1]['id'] ?? 'end');

            switch ($step['type']) {
                case 'delay':
                    $waitedKey = 'waited_'.$stepId;
                    if (! $run->is_test && empty($context[$waitedKey])) {
                        $minutes = (int) ($step['days'] ?? 0) * 1440 + (int) ($step['hours'] ?? 0) * 60;
                        $context[$waitedKey] = true;
                        $run->forceFill(['status' => 'en_attente', 'current_step' => $stepId, 'resume_at' => now()->addMinutes($minutes), 'context' => $context])->save();
                        $this->log($run, $stepId, 'delay', 'attente', "Attente jusqu'au ".now()->addMinutes($minutes)->format('d/m/Y à H:i').'.');

                        return;
                    }
                    if ($run->is_test) {
                        $this->log($run, $stepId, 'delay', 'simule', 'Délai ignoré en mode test.');
                    }
                    break;

                case 'condition':
                    $result = $this->conditions->evaluate($step, $run, $company, $opportunity);
                    $label = config('prospection.automation.conditions')[$step['field']] ?? $step['field'];
                    $actual = $this->conditions->value($step['field'], $run, $company, $opportunity);
                    $shown = is_bool($actual) ? ($actual ? 'oui' : 'non') : (string) ($actual ?? 'vide');
                    $this->log($run, $stepId, 'condition', $result ? 'remplie' : 'non remplie', "« {$label} » vaut « {$shown} » : condition ".($result ? 'remplie' : 'non remplie').'.');
                    $next = $result ? $step['then'] : $step['else'];
                    break;

                case 'action':
                    if (($step['action'] ?? null) === 'stop') {
                        $this->log($run, $stepId, 'action', 'ok', 'Arrêt demandé par le scénario.');
                        $next = 'end';
                        break;
                    }
                    $done = AutomationLog::where('run_id', $run->id)->where('step_id', $stepId)->where('type', 'action')->whereIn('result', ['ok', 'ignore'])->exists();
                    if (! $done) {
                        [$result, $message] = $this->actions->execute($step, $run, $company);
                        $this->log($run, $stepId, 'action', $run->is_test && $result === 'ok' ? 'simule' : $result, $message);
                    }
                    break;
            }

            $stepId = $next;
            $run->forceFill(['current_step' => $stepId, 'context' => $context])->save();
        }

        $this->finish($run, 'termine', null);
    }

    /** @return array{0: ?Company, 1: ?Opportunity} */
    private function resolve(AutomationRun $run): array
    {
        $subject = $run->subject_type::withoutGlobalScopes()->find($run->subject_id);

        return match (true) {
            $subject instanceof Company => [$subject->trashed() ? null : $subject, null],
            $subject instanceof Opportunity => [$subject->company, $subject],
            $subject instanceof Contact => [$subject->company, null],
            default => [null, null],
        };
    }

    private function finish(AutomationRun $run, string $status, ?string $error): void
    {
        $run->forceFill(['status' => $status, 'finished_at' => now(), 'resume_at' => null, 'error' => $error])->save();
    }

    private function log(AutomationRun $run, ?string $stepId, string $type, string $result, string $message): void
    {
        AutomationLog::create(['run_id' => $run->id, 'step_id' => $stepId, 'type' => $type, 'result' => $result, 'message' => mb_substr($message, 0, 255)]);
    }
}
