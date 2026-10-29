<?php

namespace App\Http\Controllers;

use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\Company;
use App\Models\MailAccount;
use App\Models\Template;
use App\Services\AuditLogger;
use App\Services\Automation\AutomationEngine;
use App\Services\Automation\DefinitionValidator;
use App\Services\Automation\Presets;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AutomationController extends Controller
{
    /** Champs de configuration conservés par type d'étape. */
    private const STEP_FIELDS = [
        'action' => ['action', 'title', 'message', 'task_type', 'due_days', 'status', 'tag', 'template_id', 'mail_account_id'],
        'delay' => ['days', 'hours'],
        'condition' => ['field', 'operator', 'value', 'then', 'else'],
    ];

    /** Champs propres à chaque action (les autres champs du formulaire sont ignorés). */
    private const ACTION_FIELDS = [
        'create_task' => ['title', 'task_type', 'due_days'],
        'notify_owner' => ['title', 'message'],
        'set_company_status' => ['status'],
        'add_tag' => ['tag'],
        'send_email' => ['template_id', 'mail_account_id'],
    ];

    public function __construct(private readonly AuditLogger $audit, private readonly DefinitionValidator $validator) {}

    public function index(Request $request): View
    {
        $this->guard($request);

        return view('automations.index', [
            'automations' => Automation::with('activeVersion')->withCount([
                'runs as waiting_count' => fn ($q) => $q->where('status', 'en_attente')->where('is_test', false),
                'runs as done_count' => fn ($q) => $q->where('status', 'termine')->where('is_test', false),
                'runs as error_count' => fn ($q) => $q->where('status', 'erreur')->where('is_test', false),
            ])->latest('updated_at')->get(),
            'presets' => Presets::all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->guard($request);
        $data = $request->validate(['preset' => ['nullable', Rule::in(array_keys(Presets::all()))]]);
        $preset = Presets::all()[$data['preset'] ?? ''] ?? ['name' => 'Nouveau scénario', 'trigger' => 'company.created', 'steps' => [['id' => 's1', 'type' => 'action', 'action' => 'create_task', 'title' => 'Nouvelle tâche', 'due_days' => 1]]];

        $automation = Automation::create(['name' => $preset['name'], 'trigger' => $preset['trigger']]);
        $automation->forceFill(['created_by' => $request->user()->id])->save();
        $automation->addVersion(['steps' => $preset['steps']], $request->user()->id);
        $this->audit->log('automation.created', $automation, ['nom' => $automation->name]);

        return redirect()->route('pm.automations.edit', $automation)->with('success', 'Scénario créé en brouillon : vérifiez-le, testez-le, puis activez-le.');
    }

    public function show(Request $request, Automation $automation): View
    {
        $this->guard($request);
        $automation->load(['activeVersion', 'latestVersion']);

        return view('automations.show', [
            'automation' => $automation,
            'versions' => $automation->versions()->get(),
            'runs' => $automation->runs()->with(['logs', 'version'])->latest('id')->paginate(20),
            'companies' => Company::visibleTo($request->user())->orderBy('name')->limit(200)->get(['id', 'name']),
        ]);
    }

    public function edit(Request $request, Automation $automation): View
    {
        $this->guard($request);

        return view('automations.edit', [
            'automation' => $automation,
            'steps' => $automation->latestVersion?->steps() ?? [],
            'templates' => Template::where('category', 'prospection')->orderBy('name')->get(['id', 'name']),
            'accounts' => MailAccount::where('is_active', true)->where('last_test_result', 'ok')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** Toute modification crée une nouvelle version ; les exécutions en cours gardent la leur. */
    public function update(Request $request, Automation $automation): RedirectResponse
    {
        $this->guard($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'trigger' => ['required', Rule::in(array_keys(config('prospection.automation.triggers')))],
            'max_runs_per_subject' => ['required', 'integer', 'min:1', 'max:50'],
            'frequency_days' => ['required', 'integer', 'min:1', 'max:365'],
            'steps' => ['required', 'array', 'max:30'],
            'steps.*.type' => ['required', 'string'],
        ], [], ['trigger' => 'déclencheur', 'max_runs_per_subject' => 'limite de déclenchements', 'frequency_days' => 'période']);

        if ($automation->status === 'active' && $data['trigger'] !== $automation->trigger) {
            return back()->withInput()->withErrors(['trigger' => 'Mettez le scénario en pause avant de changer son déclencheur.']);
        }
        // validate() ne renvoie que les champs listés : la configuration des étapes est lue
        // dans la requête puis filtrée par type et contrôlée par le DefinitionValidator.
        $steps = $this->normalize((array) $request->input('steps', []));
        if ($errors = $this->validator->validate($steps)) {
            return back()->withInput()->withErrors(['steps' => implode(' ', $errors)]);
        }

        $automation->update(collect($data)->only(['name', 'trigger', 'max_runs_per_subject', 'frequency_days'])->all());
        $latest = $automation->latestVersion;
        if (! $latest || $latest->steps() !== $steps) {
            $version = $automation->addVersion(['steps' => $steps], $request->user()->id);
            $this->audit->log('automation.version_saved', $automation, ['version' => $version->version]);
            $message = "Version {$version->version} enregistrée.".($automation->status === 'active' ? ' Elle s\'appliquera après « Publier la dernière version ».' : '');
        }

        return redirect()->route('pm.automations.show', $automation)->with('success', $message ?? 'Paramètres enregistrés.');
    }

    /** Active (ou republie) la dernière version : seuls les nouveaux déclenchements l'utilisent. */
    public function activate(Request $request, Automation $automation): RedirectResponse
    {
        $this->guard($request);
        $version = $automation->latestVersion;
        if (! $version || $this->validator->validate($version->steps())) {
            return back()->withErrors(['automation' => 'Le scénario contient des erreurs : corrigez-le avant de l\'activer.']);
        }
        $automation->forceFill(['status' => 'active', 'active_version_id' => $version->id])->save();
        $this->audit->log('automation.activated', $automation, ['version' => $version->version]);

        return back()->with('success', "Scénario actif (version {$version->version}).");
    }

    public function pause(Request $request, Automation $automation): RedirectResponse
    {
        $this->guard($request);
        abort_unless($automation->status === 'active', 409);
        $automation->forceFill(['status' => 'en_pause'])->save();
        $this->audit->log('automation.paused', $automation);

        return back()->with('success', 'Scénario en pause : aucun nouveau déclenchement, les exécutions en attente patientent.');
    }

    public function resume(Request $request, Automation $automation): RedirectResponse
    {
        $this->guard($request);
        abort_unless($automation->status === 'en_pause', 409);
        $automation->forceFill(['status' => 'active'])->save();
        $this->audit->log('automation.resumed', $automation);

        return back()->with('success', 'Scénario repris.');
    }

    /** Arrêt : plus aucun déclenchement et toutes les exécutions en attente sont arrêtées. */
    public function stop(Request $request, Automation $automation): RedirectResponse
    {
        $this->guard($request);
        $automation->forceFill(['status' => 'arretee'])->save();
        $stopped = AutomationRun::where('automation_id', $automation->id)->whereIn('status', ['en_attente', 'en_cours'])
            ->update(['status' => 'arrete', 'finished_at' => now(), 'error' => 'Scénario arrêté manuellement']);
        $this->audit->log('automation.stopped', $automation, ['exécutions_arrêtées' => $stopped]);

        return back()->with('success', "Scénario arrêté ({$stopped} exécution(s) en attente interrompue(s)).");
    }

    /** Mode test : déroule la dernière version sur une fiche, sans rien modifier. */
    public function test(Request $request, Automation $automation, AutomationEngine $engine): RedirectResponse
    {
        $this->guard($request);
        $company = Company::visibleTo($request->user())->findOrFail($request->validate(['company_id' => ['required', 'integer']])['company_id']);
        $version = $automation->latestVersion;
        if (! $version || ($errors = $this->validator->validate($version->steps()))) {
            return back()->withErrors(['automation' => 'Le scénario contient des erreurs : '.implode(' ', $errors ?? [])]);
        }
        $run = $engine->test($automation, $company, $version->id);

        return redirect()->to(route('pm.automations.show', $automation).'#run-'.$run->id)->with('success', 'Test terminé : consultez le déroulé ci-dessous (aucune modification effectuée).');
    }

    public function destroy(Request $request, Automation $automation): RedirectResponse
    {
        $this->guard($request);
        AutomationRun::where('automation_id', $automation->id)->whereIn('status', ['en_attente', 'en_cours'])->update(['status' => 'arrete', 'finished_at' => now()]);
        $automation->forceFill(['status' => 'arretee'])->save();
        $automation->delete();
        $this->audit->log('automation.deleted', $automation, ['nom' => $automation->name]);

        return redirect()->route('pm.automations.index')->with('success', 'Scénario supprimé.');
    }

    /**
     * Étapes saisies dans le formulaire → définition propre (identifiants s1, s2… par position).
     *
     * @return array<int, array<string, mixed>>
     */
    private function normalize(array $input): array
    {
        $steps = [];
        foreach (array_values($input) as $i => $raw) {
            $type = (string) ($raw['type'] ?? '');
            $step = ['id' => 's'.($i + 1), 'type' => $type];
            $fields = self::STEP_FIELDS[$type] ?? [];
            if ($type === 'action') {
                $fields = array_merge(['action'], self::ACTION_FIELDS[(string) ($raw['action'] ?? '')] ?? []);
            }
            if ($type === 'condition' && ! in_array($raw['operator'] ?? '', ['equals', 'not_equals'], true)) {
                $fields = array_diff($fields, ['value']);
            }
            foreach ($fields as $field) {
                $value = $raw[$field] ?? null;
                if ($value === null || $value === '') {
                    continue;
                }
                $step[$field] = in_array($field, ['due_days', 'days', 'hours', 'template_id', 'mail_account_id'], true)
                    ? max(0, (int) $value)
                    : mb_substr(trim((string) $value), 0, 255);
            }
            $steps[] = $step;
        }

        return $steps;
    }

    private function guard(Request $request): void
    {
        abort_unless($request->user()->can('automations.manage'), 403);
    }
}
