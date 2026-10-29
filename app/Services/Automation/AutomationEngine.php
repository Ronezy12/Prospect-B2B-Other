<?php

namespace App\Services\Automation;

use App\Jobs\RunAutomation;
use App\Models\Automation;
use App\Models\AutomationRun;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Point d'entrée des déclencheurs. Pour chaque scénario actif sur ce déclencheur :
 *  - limite de fréquence par fiche (N exécutions au plus sur la période) ;
 *  - dédoublonnage : un même événement ne crée jamais deux exécutions (contrainte unique) ;
 *  - exécution asynchrone et persistante (reprise après redémarrage).
 * Une erreur ici ne doit jamais faire échouer l'action métier qui a déclenché l'événement.
 */
class AutomationEngine
{
    /** @return int nombre d'exécutions créées */
    public function fire(string $trigger, Model $subject, string $eventKey, array $payload = []): int
    {
        try {
            $created = 0;
            Automation::where('trigger', $trigger)->where('status', 'active')->whereNotNull('active_version_id')->get()
                ->each(function (Automation $automation) use ($subject, $eventKey, $payload, &$created) {
                    if ($this->limitReached($automation, $subject)) {
                        return;
                    }
                    $run = $this->createRun($automation, $automation->active_version_id, $subject, $eventKey, $payload, false);
                    if ($run) {
                        $created++;
                        RunAutomation::dispatch($run->id);
                    }
                });

            return $created;
        } catch (Throwable $e) {
            report($e);

            return 0;
        }
    }

    /** Exécution de test sur une fiche : actions simulées, délais ignorés. */
    public function test(Automation $automation, Model $subject, int $versionId): AutomationRun
    {
        $run = $this->createRun($automation, $versionId, $subject, 'test:'.uniqid('', true), ['test' => true], true);
        app(AutomationRunner::class)->process($run);

        return $run->refresh();
    }

    private function limitReached(Automation $automation, Model $subject): bool
    {
        return AutomationRun::where('automation_id', $automation->id)
            ->where('subject_type', $subject->getMorphClass())->where('subject_id', $subject->getKey())
            ->where('is_test', false)
            ->where('created_at', '>=', now()->subDays($automation->frequency_days))
            ->count() >= $automation->max_runs_per_subject;
    }

    private function createRun(Automation $automation, int $versionId, Model $subject, string $eventKey, array $payload, bool $test): ?AutomationRun
    {
        $inserted = DB::table('pm_automation_runs')->insertOrIgnore([
            'automation_id' => $automation->id,
            'automation_version_id' => $versionId,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'trigger_key' => mb_substr($eventKey, 0, 190),
            'status' => 'en_attente',
            'resume_at' => now(),
            'context' => json_encode(['payload' => $payload]),
            'is_test' => $test,
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $inserted ? AutomationRun::where('automation_version_id', $versionId)
            ->where('subject_type', $subject->getMorphClass())->where('subject_id', $subject->getKey())
            ->where('trigger_key', mb_substr($eventKey, 0, 190))->first() : null;
    }
}
