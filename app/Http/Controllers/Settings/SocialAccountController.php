<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Services\AuditLogger;
use App\Services\Social\MetaClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Connexion officielle Meta : Page Facebook (Messenger) et compte Instagram professionnel lié.
 * Les secrets sont chiffrés en base ; un champ laissé vide conserve la valeur enregistrée.
 */
class SocialAccountController extends Controller
{
    private const SECRETS = ['app_secret', 'page_access_token', 'verify_token'];

    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $this->guard($request);

        return view('settings.social.index', ['accounts' => SocialAccount::orderBy('name')->get()]);
    }

    public function create(Request $request): View
    {
        $this->guard($request);

        return view('settings.social.form', ['account' => new SocialAccount(['is_active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->guard($request);
        $account = new SocialAccount;
        $account->webhook_token = Str::random(48);
        $this->save($request, $account, true);
        $this->audit->log('social_account.created', $account, ['nom' => $account->name]);

        return redirect()->route('pm.settings.social.edit', $account)->with('success', 'Connexion enregistrée. Déclarez l\'adresse du webhook dans votre application Meta, puis testez la connexion.');
    }

    public function edit(Request $request, SocialAccount $account): View
    {
        $this->guard($request);

        return view('settings.social.form', ['account' => $account]);
    }

    public function update(Request $request, SocialAccount $account): RedirectResponse
    {
        $this->guard($request);
        $this->save($request, $account, false);
        $this->audit->log('social_account.updated', $account);

        return redirect()->route('pm.settings.social.edit', $account)->with('success', 'Connexion enregistrée.');
    }

    public function test(Request $request, SocialAccount $account, MetaClient $meta): RedirectResponse
    {
        $this->guard($request);
        $result = $meta->test($account);
        $account->forceFill(['last_tested_at' => now(), 'last_test_result' => $result['ok'] ? 'ok' : $result['error']])->save();
        $this->audit->log('social_account.tested', $account, ['résultat' => $result['ok'] ? 'ok' : 'échec']);

        return $result['ok']
            ? back()->with('success', 'Connexion réussie à la Page « '.$result['name'].' ».')
            : back()->withErrors(['account' => $result['error']]);
    }

    private function save(Request $request, SocialAccount $account, bool $creating): void
    {
        $required = $creating ? 'required' : 'nullable';
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'app_id' => ['required', 'string', 'max:40', 'regex:/^\d+$/'],
            'app_secret' => [$required, 'string', 'max:255'],
            'page_id' => ['required', 'string', 'max:40', 'regex:/^\d+$/'],
            'instagram_id' => ['nullable', 'string', 'max:40', 'regex:/^\d+$/'],
            'page_access_token' => [$required, 'string', 'max:1000'],
            'verify_token' => [$required, 'string', 'min:16', 'max:128', 'regex:/^[A-Za-z0-9_-]+$/'],
            'graph_version' => ['nullable', 'string', 'regex:/^v\d{1,2}\.\d$/'],
            'is_active' => ['nullable', 'boolean'],
        ], ['verify_token.regex' => 'Le jeton de vérification ne doit contenir que des lettres, chiffres, tirets et soulignés.'], [
            'app_id' => 'identifiant de l\'application', 'app_secret' => 'secret de l\'application', 'page_id' => 'identifiant de la Page',
            'instagram_id' => 'identifiant Instagram', 'page_access_token' => 'jeton d\'accès de la Page', 'verify_token' => 'jeton de vérification', 'graph_version' => 'version de l\'API',
        ]);

        $settings = $account->settings ?? [];
        foreach (['app_id', 'page_id', 'instagram_id', 'graph_version'] as $key) {
            $settings[$key] = $data[$key] ?? null;
        }
        foreach (self::SECRETS as $key) {
            if (filled($data[$key] ?? null)) {
                $settings[$key] = $data[$key];
            }
        }
        $account->fill(['name' => $data['name'], 'is_active' => (bool) ($data['is_active'] ?? false)]);
        $account->settings = $settings;
        $account->save();
    }

    private function guard(Request $request): void
    {
        abort_unless($request->user()->can('settings.manage'), 403);
    }
}
