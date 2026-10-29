<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Inbox;
use App\Services\AuditLogger;
use App\Services\Replies\ReplyDetector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Boîtes de réception consultées en IMAP (lecture des en-têtes uniquement). */
class InboxController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $this->guard($request);

        return view('settings.inboxes.index', ['inboxes' => Inbox::orderBy('name')->get()]);
    }

    public function create(Request $request): View
    {
        $this->guard($request);

        return view('settings.inboxes.form', ['inbox' => new Inbox(['folder' => 'INBOX', 'is_active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->guard($request);
        $inbox = new Inbox;
        $this->save($request, $inbox, true);
        $this->audit->log('inbox.created', $inbox, ['nom' => $inbox->name]);

        return redirect()->route('pm.settings.inboxes.index')->with('success', 'Boîte de réception ajoutée. Lancez une vérification pour tester la connexion.');
    }

    public function edit(Request $request, Inbox $inbox): View
    {
        $this->guard($request);

        return view('settings.inboxes.form', ['inbox' => $inbox]);
    }

    public function update(Request $request, Inbox $inbox): RedirectResponse
    {
        $this->guard($request);
        $this->save($request, $inbox, false);
        $this->audit->log('inbox.updated', $inbox);

        return redirect()->route('pm.settings.inboxes.index')->with('success', 'Boîte de réception enregistrée.');
    }

    public function check(Request $request, Inbox $inbox, ReplyDetector $detector): RedirectResponse
    {
        $this->guard($request);
        $result = $detector->check($inbox);
        $inbox->refresh();

        return str_starts_with((string) $inbox->last_result, 'ok')
            ? back()->with('success', "Connexion réussie : {$result['lus']} message(s) lu(s), {$result['reponses']} réponse(s) rattachée(s).")
            : back()->withErrors(['inbox' => $inbox->last_result]);
    }

    private function save(Request $request, Inbox $inbox, bool $creating): void
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'host' => ['required', 'string', 'max:190', 'regex:/^[a-z0-9.-]+$/i'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'encryption' => ['required', Rule::in(['ssl', 'tls', 'none'])],
            'username' => ['required', 'string', 'max:190'],
            'password' => [$creating ? 'required' : 'nullable', 'string', 'max:255'],
            'folder' => ['required', 'string', 'max:120'],
            'is_active' => ['nullable', 'boolean'],
        ], [], ['host' => 'serveur IMAP', 'username' => 'identifiant', 'password' => 'mot de passe', 'folder' => 'dossier']);

        $settings = $inbox->settings ?? [];
        foreach (['host', 'port', 'encryption', 'username'] as $key) {
            $settings[$key] = $data[$key];
        }
        if (filled($data['password'] ?? null)) {
            $settings['password'] = $data['password'];
        }
        $inbox->fill(['name' => $data['name'], 'folder' => $data['folder'], 'is_active' => (bool) ($data['is_active'] ?? false)]);
        $inbox->settings = $settings;
        $inbox->save();
    }

    private function guard(Request $request): void
    {
        abort_unless($request->user()->can('settings.manage'), 403);
    }
}
