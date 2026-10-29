<?php

namespace App\Services\Automation;

use App\Models\AutomationRun;
use App\Models\Campaign;
use App\Models\Company;
use App\Models\MailAccount;
use App\Models\Opportunity;
use App\Models\Tag;
use App\Models\Task;
use App\Models\Template;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\ActivityRecorder;
use App\Services\AuditLogger;
use App\Services\Mail\CampaignService;
use App\Services\Mail\ComplianceGate;

/**
 * Actions des scénarios. Chacune renvoie un message pour le journal. En mode test,
 * rien n'est modifié : l'action est seulement décrite. L'envoi d'e-mail passe par la
 * même chaîne que les campagnes, contrôle de conformité compris.
 */
class ActionExecutor
{
    public function __construct(
        private readonly ActivityRecorder $activities,
        private readonly AuditLogger $audit,
        private readonly CampaignService $campaigns,
        private readonly ComplianceGate $gate,
    ) {}

    /** @return array{0: string, 1: string} [résultat ok|ignore, message] */
    public function execute(array $step, AutomationRun $run, ?Company $company): array
    {
        $action = $step['action'];
        $simulate = $run->is_test;
        $label = "Scénario « {$run->automation->name} »";

        if (! $company && $action !== 'stop') {
            return ['ignore', 'Aucune entreprise liée : action ignorée.'];
        }

        switch ($action) {
            case 'create_task':
                $due = now()->addDays((int) ($step['due_days'] ?? 1))->setTime(9, 0);
                $assignee = $company->owner_id ?? $run->automation->created_by;
                if ($simulate) {
                    return ['ok', "Créerait la tâche « {$step['title']} » pour le {$due->format('d/m/Y')}."];
                }
                $task = new Task(['title' => mb_substr($step['title'], 0, 190), 'type' => $step['task_type'] ?? 'relance', 'priority' => $step['priority'] ?? 'normale', 'due_at' => $due, 'description' => $label]);
                $task->company_id = $company->id;
                $task->opportunity_id = $run->subject_type === (new Opportunity)->getMorphClass() ? $run->subject_id : null;
                $task->assignee_id = $assignee;
                $task->created_by = $run->automation->created_by;
                $task->save();
                $this->activities->record($company, 'systeme', "{$label} : tâche créée « {$task->title} ».", ['automation_run_id' => $run->id], null);

                return ['ok', "Tâche « {$task->title} » créée (échéance {$due->format('d/m/Y')})."];

            case 'notify_owner':
                $userId = $company->owner_id ?? $run->automation->created_by;
                if ($simulate) {
                    return ['ok', 'Notifierait '.(User::find($userId)?->name ?? 'personne').' : « '.$step['title'].' ».'];
                }
                UserNotification::notify($userId, $step['title'].' — '.$company->name, $step['message'] ?? null, route('pm.companies.show', $company, false));

                return ['ok', 'Notification envoyée à '.(User::find($userId)?->name ?? 'personne').'.'];

            case 'set_company_status':
                $old = $company->status;
                if ($simulate) {
                    return ['ok', "Passerait le statut de « {$company->statusLabel()} » à « ".config('prospection.company_statuses')[$step['status']].' ».'];
                }
                $company->forceFill(['status' => $step['status']])->save();
                $this->activities->record($company, 'systeme', "{$label} : statut changé en « {$company->statusLabel()} ».", ['automation_run_id' => $run->id], null);
                $this->audit->log('company.updated', $company, ['status' => ['avant' => $old, 'après' => $step['status']], 'origine' => 'automatisation #'.$run->automation_id], $run->automation->created_by);

                return ['ok', "Statut changé en « {$company->statusLabel()} »."];

            case 'add_tag':
                if ($simulate) {
                    return ['ok', "Ajouterait le tag « {$step['tag']} »."];
                }
                $company->tags()->syncWithoutDetaching(Tag::idsFromString($step['tag']));

                return ['ok', "Tag « {$step['tag']} » ajouté."];

            case 'send_email':
                return $this->sendEmail($step, $run, $company, $simulate);

            case 'stop_followups':
                $tasks = Task::where('company_id', $company->id)->where('status', 'a_faire')->where('type', 'relance');
                $runs = AutomationRun::where('subject_type', $company->getMorphClass())->where('subject_id', $company->id)
                    ->where('status', 'en_attente')->whereKeyNot($run->id)->where('is_test', false);
                if ($simulate) {
                    return ['ok', 'Annulerait '.$tasks->count().' relance(s) et arrêterait '.$runs->count().' scénario(s) en attente.'];
                }
                $taskCount = $tasks->update(['status' => 'annule', 'completed_at' => now()]);
                $runCount = $runs->update(['status' => 'arrete', 'finished_at' => now(), 'error' => 'Arrêté : relances suspendues']);
                $this->activities->record($company, 'systeme', "{$label} : relances suspendues ({$taskCount} tâche(s), {$runCount} scénario(s)).", ['automation_run_id' => $run->id], null);

                return ['ok', "{$taskCount} relance(s) annulée(s), {$runCount} scénario(s) arrêté(s)."];

            case 'convert_to_client':
                if ($company->isClient()) {
                    return ['ignore', 'Déjà client.'];
                }
                if ($simulate) {
                    return ['ok', 'Enregistrerait la conversion en client.'];
                }
                $company->forceFill(['lifecycle' => 'client', 'converted_at' => now(), 'converted_by' => null])->save();
                $this->activities->record($company, 'systeme', "{$label} : conversion en client enregistrée.", ['automation_run_id' => $run->id], null);
                $this->audit->log('company.converted', $company, ['origine' => 'automatisation #'.$run->automation_id], $run->automation->created_by);

                return ['ok', 'Conversion en client enregistrée.'];
        }

        return ['ignore', 'Action non prise en charge.'];
    }

    private function sendEmail(array $step, AutomationRun $run, Company $company, bool $simulate): array
    {
        $contact = $company->contacts()->whereNotNull('email')->orderByDesc('is_primary')->first();
        if (! $contact) {
            return ['ignore', 'Aucun contact avec e-mail.'];
        }
        $template = Template::find($step['template_id']);
        $account = MailAccount::where('is_active', true)->where('last_test_result', 'ok')->find($step['mail_account_id']);
        if (! $template?->latestVersion || ! $account) {
            return ['ignore', 'Modèle ou compte d\'envoi indisponible.'];
        }
        if ($reason = $this->gate->check(new Campaign(['type' => 'individuel']), $contact)) {
            return ['ignore', 'E-mail non envoyé : '.(config('prospection.exclusion_reasons')[$reason] ?? $reason).'.'];
        }
        if ($simulate) {
            return ['ok', "Enverrait « {$template->latestVersion->subject} » à {$contact->email}."];
        }

        $author = User::find($run->automation->created_by) ?? User::role('administrateur')->first();
        $campaign = $this->campaigns->sendTemplateToContact($contact, $account, $template, $author, "Scénario « {$run->automation->name} »");

        return ['ok', "E-mail « {$template->latestVersion->subject} » mis en envoi pour {$contact->email} (campagne #{$campaign->id})."];
    }
}
