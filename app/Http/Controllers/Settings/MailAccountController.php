<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\MailAccount;
use App\Services\AuditLogger;
use App\Services\Mail\MailProviderFactory;
use App\Services\Mail\OutgoingMessage;
use App\Support\HtmlSanitizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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

    /** Aperçu de la signature HTML nettoyée, affiché dans un cadre isolé (aucun script possible). */
    public function signaturePreview(Request $request): Response
    {
        $this->guard($request);
        $html = HtmlSanitizer::clean((string) $request->input('signature_html', ''));
        $body = $html !== '' ? $html : '<p style="font:14px Arial,sans-serif;color:#6a7690">Aucune signature HTML : collez ou glissez votre code, puis cliquez sur « Aperçu ».</p>';

        return response('<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><title>Aperçu</title></head><body style="margin:12px;background:#fff">'.$body.'</body></html>')
            ->header('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; img-src https: data:; font-src https: data:; frame-ancestors 'self'; sandbox");
    }

    private function save(Request $request, MailAccount $account): void
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'driver' => ['required', Rule::in(array_keys($this->drivers()))],
            'from_email' => ['required', 'email', 'max:190'],
            'from_name' => ['required', 'string', 'max:120'],
            'reply_to' => ['nullable', 'email', 'max:190'],
            'signature' => ['nullable', 'string', 'max:2000'],
            'signature_html' => ['nullable', 'string', 'max:60000'],
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
        // Gmail : le mot de passe d'application s'affiche par groupes de 4 lettres (« abcd efgh … »),
        // les espaces copiés avec lui sont retirés ; l'identifiant est l'adresse complète.
        if (str_ends_with(strtolower((string) ($settings['host'] ?? '')), 'gmail.com')) {
            if (filled($settings['password'] ?? null)) {
                $settings['password'] = preg_replace('/\s+/', '', $settings['password']);
            }
            // Un identifiant qui n'est pas une adresse (ou rempli par le navigateur avec un autre compte) est remplacé par l'adresse d'expédition.
            $username = trim((string) ($settings['username'] ?? ''));
            $settings['username'] = filter_var($username, FILTER_VALIDATE_EMAIL) && ! str_ends_with(strtolower($username), '.test') ? $username : $data['from_email'];
        }

        // Jeton secret de l'URL de webhook (Brevo) : généré une fois, jamais modifiable par formulaire.
        if ($data['driver'] === 'brevo' && blank($settings['webhook_token'] ?? null)) {
            $settings['webhook_token'] = Str::random(48);
        }
        $account->fill(collect($data)->only(['name', 'driver', 'from_email', 'from_name', 'reply_to', 'signature', 'hourly_limit', 'daily_limit'])->all());
        $account->signature_html = filled($data['signature_html'] ?? null) ? (HtmlSanitizer::clean($data['signature_html']) ?: null) : null;
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
