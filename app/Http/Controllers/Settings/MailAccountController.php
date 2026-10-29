<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\MailAccount;
use App\Services\AuditLogger;
use App\Services\Mail\MailProviderFactory;
use App\Services\Mail\OutgoingMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MailAccountController extends Controller
{
    private const SECRETS = ['password', 'api_key'];

    public function __construct(private readonly AuditLogger $audit, private readonly MailProviderFactory $providers) {}

    public function index(Request $request): View
    {
        $this->guard($request);

        return view('settings.mail.index', ['accounts' => MailAccount::orderBy('name')->get()]);
    }

    public function create(Request $request): View
    {
        $this->guard($request);

        return view('settings.mail.form', ['account' => new MailAccount(['driver' => 'smtp', 'hourly_limit' => 200, 'daily_limit' => 1000, 'is_active' => true]), 'drivers' => $this->drivers()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->guard($request);
        $account = new MailAccount;
        $this->save($request, $account);
        $this->audit->log('mail_account.created', $account, ['nom' => $account->name, 'type' => $account->driver]);

        return redirect()->route('pm.settings.mail.index')->with('success', 'Compte d\'envoi créé. Envoyez un e-mail de test pour le valider.');
    }

    public function edit(Request $request, MailAccount $account): View
    {
        $this->guard($request);

        return view('settings.mail.form', ['account' => $account, 'drivers' => $this->drivers()]);
    }

    public function update(Request $request, MailAccount $account): RedirectResponse
    {
        $this->guard($request);
        $this->save($request, $account);
        $this->audit->log('mail_account.updated', $account, array_keys($this->audit->diff($account)));

        return redirect()->route('pm.settings.mail.index')->with('success', 'Compte d\'envoi enregistré.');
    }

    /** Envoi réel d'un message de test : seul moyen fiable de valider la configuration. */
    public function test(Request $request, MailAccount $account): RedirectResponse
    {
        $this->guard($request);
        $to = $request->validate(['test_email' => ['required', 'email']])['test_email'];

        $result = $this->providers->for($account)->send(new OutgoingMessage(
            $to, null, $account->from_email, $account->from_name, $account->reply_to,
            '[TEST] Configuration du compte « '.$account->name.' »',
            '<p>Ce message confirme que le compte d\'envoi <strong>'.e($account->name).'</strong> fonctionne.</p>',
            'Ce message confirme que le compte d\'envoi '.$account->name.' fonctionne.',
            'account-test-'.Str::uuid(),
        ));

        $account->forceFill([
            'last_tested_at' => now(),
            'last_test_result' => $result->isAccepted() ? 'ok' : mb_substr((string) $result->error, 0, 255),
        ])->save();
        $this->audit->log('mail_account.tested', $account, ['résultat' => $result->isAccepted() ? 'ok' : 'échec']);

        return $result->isAccepted()
            ? back()->with('success', "Message de test accepté par le serveur d'envoi pour {$to}. Vérifiez sa réception (et le dossier indésirables).")
            : back()->withErrors(['test_email' => 'Échec du test : '.$result->error]);
    }

    private function save(Request $request, MailAccount $account): void
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'driver' => ['required', Rule::in(array_keys($this->drivers()))],
            'from_email' => ['required', 'email', 'max:190'],
            'from_name' => ['required', 'string', 'max:120'],
            'reply_to' => ['nullable', 'email', 'max:190'],
            'hourly_limit' => ['required', 'integer', 'min:1', 'max:100000'],
            'daily_limit' => ['required', 'integer', 'min:1', 'max:1000000'],
            'is_active' => ['nullable', 'boolean'],
            'host' => ['required_if:driver,smtp', 'nullable', 'string', 'max:190', 'regex:/^[a-z0-9.-]+$/i'],
            'port' => ['required_if:driver,smtp', 'nullable', 'integer', 'between:1,65535'],
            'encryption' => ['nullable', Rule::in(['tls', 'ssl', 'none'])],
            'username' => ['nullable', 'string', 'max:190'],
            'password' => ['nullable', 'string', 'max:255'],
            'api_key' => ['nullable', 'string', 'max:255'],
        ], [], ['from_email' => 'adresse d\'expédition', 'from_name' => 'nom d\'expéditeur', 'host' => 'serveur SMTP', 'hourly_limit' => 'limite horaire', 'daily_limit' => 'limite journalière']);

        $settings = $account->settings ?? [];
        foreach (['host', 'port', 'encryption', 'username'] as $key) {
            $settings[$key] = $data[$key] ?? null;
        }
        // Un secret laissé vide est conservé ; il n'est jamais réaffiché.
        foreach (self::SECRETS as $key) {
            if (filled($data[$key] ?? null)) {
                $settings[$key] = $data[$key];
            }
        }

        $account->fill(collect($data)->only(['name', 'driver', 'from_email', 'from_name', 'reply_to', 'hourly_limit', 'daily_limit'])->all());
        $account->is_active = (bool) ($data['is_active'] ?? false);
        $account->settings = $settings;
        // Toute modification de la configuration impose un nouveau test avant envoi.
        $account->last_tested_at = null;
        $account->last_test_result = null;
        $account->save();
        $this->providers->forget($account);
    }

    private function drivers(): array
    {
        $drivers = config('prospection.mail_drivers');
        if (app()->environment('production')) {
            unset($drivers['log']);
        }

        return $drivers;
    }

    private function guard(Request $request): void
    {
        abort_unless($request->user()->can('settings.manage'), 403);
    }
}
