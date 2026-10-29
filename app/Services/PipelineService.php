<?php

namespace App\Services;

use App\Models\Opportunity;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\Sector;
use App\Models\StageHistory;
use App\Models\User;
use App\Services\Automation\AutomationEngine;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Règles des pipelines : chaque secteur racine possède sa pipeline, une
 * opportunité ne change d'étape qu'à l'intérieur de sa pipeline, et chaque
 * changement d'étape est historisé.
 */
class PipelineService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly ActivityRecorder $activities,
    ) {}

    public function ensureForSector(Sector $sector): Pipeline
    {
        $pipeline = Pipeline::firstOrCreate(['sector_id' => $sector->id], ['name' => $sector->name]);
        $pipeline->seedDefaultStages();

        return $pipeline;
    }

    public function create(Opportunity $opportunity, Pipeline $pipeline, PipelineStage $stage, User $user): Opportunity
    {
        $this->assertStageBelongs($stage, $pipeline);

        return DB::transaction(function () use ($opportunity, $pipeline, $stage, $user) {
            $opportunity->pipeline_id = $pipeline->id;
            $opportunity->stage_id = $stage->id;
            $opportunity->created_by = $user->id;
            $opportunity->position = (int) Opportunity::where('stage_id', $stage->id)->max('position') + 1;
            $opportunity->closed_at = $stage->isClosing() ? now() : null;
            $opportunity->save();

            $this->recordHistory($opportunity, null, $stage, $user);
            $this->activities->record($opportunity->company, 'systeme', "Opportunité créée : « {$opportunity->title} » ({$pipeline->name} › {$stage->name}).", ['opportunity_id' => $opportunity->id], $user->id);
            $this->audit->log('opportunity.created', $opportunity, ['étape' => $stage->name], $user->id);

            return $opportunity;
        });
    }

    /**
     * Déplace une opportunité vers une étape (et une position dans la colonne).
     */
    public function move(Opportunity $opportunity, PipelineStage $stage, ?int $position, User $user): bool
    {
        $this->assertStageBelongs($stage, $opportunity->pipeline);

        return DB::transaction(function () use ($opportunity, $stage, $position, $user) {
            $from = $opportunity->stage;
            $changed = $from->id !== $stage->id;

            $siblings = Opportunity::where('stage_id', $stage->id)
                ->whereKeyNot($opportunity->id)
                ->orderBy('position')->orderBy('id')
                ->pluck('id')->all();
            $position = $position === null ? count($siblings) : max(0, min($position, count($siblings)));
            array_splice($siblings, $position, 0, [$opportunity->id]);

            $opportunity->stage_id = $stage->id;
            if ($changed) {
                $opportunity->closed_at = $stage->isClosing() ? now() : null;
                if ($stage->type !== 'lost') {
                    $opportunity->lost_reason = null;
                }
            }
            $opportunity->save();

            foreach ($siblings as $index => $id) {
                Opportunity::whereKey($id)->update(['position' => $index]);
            }

            if ($changed) {
                $history = $this->recordHistory($opportunity, $from, $stage, $user);
                DB::afterCommit(function () use ($opportunity, $stage, $history) {
                    $engine = app(AutomationEngine::class);
                    $engine->fire('opportunity.stage_changed', $opportunity, 'stage:'.$history->id, ['stage' => $stage->name]);
                    if ($stage->type === 'won') {
                        $engine->fire('opportunity.won', $opportunity, 'won:'.$history->id);
                    }
                });
                $this->activities->record($opportunity->company, 'systeme', "Opportunité « {$opportunity->title} » : {$from->name} → {$stage->name}.", ['opportunity_id' => $opportunity->id], $user->id);
                $this->audit->log('opportunity.stage_changed', $opportunity, ['avant' => $from->name, 'après' => $stage->name], $user->id);
            }

            return $changed;
        });
    }

    private function recordHistory(Opportunity $opportunity, ?PipelineStage $from, PipelineStage $to, User $user): StageHistory
    {
        return StageHistory::create([
            'opportunity_id' => $opportunity->id,
            'from_stage_id' => $from?->id,
            'to_stage_id' => $to->id,
            'from_name' => $from?->name,
            'to_name' => $to->name,
            'user_id' => $user->id,
            'changed_at' => now(),
        ]);
    }

    private function assertStageBelongs(PipelineStage $stage, Pipeline $pipeline): void
    {
        if ($stage->pipeline_id !== $pipeline->id) {
            throw new InvalidArgumentException('Cette étape n\'appartient pas à la pipeline de l\'opportunité.');
        }
    }
}
