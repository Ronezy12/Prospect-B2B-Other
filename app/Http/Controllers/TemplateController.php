<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\MailAccount;
use App\Models\Template;
use App\Models\TemplateVersion;
use App\Services\AuditLogger;
use App\Services\Mail\CampaignService;
use App\Services\Mail\TemplateRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TemplateController extends Controller
{
    public const CATEGORIES = ['prospection' => 'Prospection', 'newsletter' => 'Newsletter', 'operationnel' => 'Opérationnel'];

    private const STARTERS = ['prospection' => 'Prospection (e-mail commercial)', 'newsletter' => 'Newsletter', 'vierge' => 'Page vierge'];

    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $this->guard($request, 'campaigns.view');

        return view('templates.index', [
            'templates' => Template::where('category', '!=', 'individuel')->with('latestVersion')->latest('updated_at')->paginate(config('prospection.per_page')),
        ]);
    }

    public function create(Request $request): View
    {
        $this->guard($request);

        return view('templates.create', ['starters' => self::STARTERS]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->guard($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'category' => ['required', Rule::in(array_keys(self::CATEGORIES))],
            'starter' => ['required', Rule::in(array_keys(self::STARTERS))],
        ], [], ['name' => 'nom du modèle', 'category' => 'catégorie']);

        $template = Template::create($data);
        $template->forceFill(['created_by' => $request->user()->id])->save();
        $template->addVersion([
            'subject' => $data['category'] === 'newsletter' ? 'Les actualités d\'Entreprise AR' : '{{prenom}}, une proposition pour {{entreprise}}',
            'html' => file_get_contents(resource_path('email-starters/'.$data['starter'].'.html')),
        ], $request->user()->id);
        $this->audit->log('template.created', $template, ['nom' => $template->name]);

        return redirect()->route('pm.templates.edit', $template)->with('success', 'Modèle créé : personnalisez son contenu puis enregistrez.');
    }

    public function show(Request $request, Template $template): View
    {
        $this->guard($request, 'campaigns.view');
        $version = $this->version($request, $template);

        return view('templates.show', [
            'template' => $template,
            'version' => $version,
            'versions' => $template->versions()->with('author:id,name')->get(),
            'accounts' => MailAccount::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    /** Éditeur visuel (GrapesJS). */
    public function edit(Request $request, Template $template): Response
    {
        $this->guard($request);

        return response()->view('templates.editor', [
            'template' => $template,
            'version' => $template->latestVersion,
            'variables' => config('prospection.template_variables'),
        ])->header('Content-Security-Policy', implode('; ', [
            // L'éditeur injecte ses styles et son canevas : CSP élargie sur cette seule page.
            "default-src 'self'", "script-src 'self' 'unsafe-eval'", "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: https:", "font-src 'self' data:", "frame-src 'self' about:", "connect-src 'self'",
            "frame-ancestors 'self'", "form-action 'self'", "base-uri 'self'",
        ]));
    }

    /** Chaque enregistrement crée une nouvelle version : les campagnes envoyées gardent la leur. */
    public function update(Request $request, Template $template): RedirectResponse
    {
        $this->guard($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'subject' => ['required', 'string', 'max:190'],
            'preheader' => ['nullable', 'string', 'max:190'],
            'html' => ['required', 'string', 'max:1000000'],
            'design' => ['nullable', 'string', 'max:2000000', 'json'],
        ], [], ['subject' => 'objet', 'preheader' => 'texte d\'aperçu', 'html' => 'contenu']);

        $template->update(['name' => $data['name']]);
        $latest = $template->latestVersion;
        $html = $this->sanitize($data['html']);
        if ($latest && $latest->subject === $data['subject'] && $latest->html === $html && $latest->preheader === ($data['preheader'] ?? null)) {
            return redirect()->route('pm.templates.edit', $template)->with('success', 'Aucune modification à enregistrer.');
        }
        $version = $template->addVersion([
            'subject' => $data['subject'],
            'preheader' => $data['preheader'] ?? null,
            'html' => $html,
            'design' => $data['design'] ?? null,
        ], $request->user()->id);
        $this->audit->log('template.version_saved', $template, ['version' => $version->version]);

        return redirect()->route('pm.templates.edit', $template)->with('success', "Version {$version->version} enregistrée.");
    }

    /** Rendu brut avec données d'exemple, affiché dans un cadre isolé (sans script). */
    public function preview(Request $request, Template $template, TemplateRenderer $renderer): Response
    {
        $this->guard($request, 'campaigns.view');
        $version = $this->version($request, $template);
        $rendered = $renderer->render($version->subject, $version->html, $version->preheader, TemplateRenderer::sampleData('Entreprise AR'), $template->category !== 'operationnel', 'Entreprise AR');

        return response('<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.e($rendered['subject']).'</title></head><body style="margin:0">'.$rendered['html'].'</body></html>')
            ->header('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; img-src https: data:; font-src https: data:; frame-ancestors 'self'; sandbox");
    }

    public function test(Request $request, Template $template, CampaignService $campaigns): RedirectResponse
    {
        $this->guard($request);
        $data = $request->validate([
            'mail_account_id' => ['required', Rule::exists('pm_mail_accounts', 'id')->where('is_active', true)],
            'emails' => ['required', 'string', 'max:1000'],
        ], [], ['mail_account_id' => 'compte d\'envoi', 'emails' => 'destinataires']);

        $emails = $this->parseEmails($data['emails']);
        if ($emails === null) {
            return back()->withErrors(['emails' => 'Indiquez jusqu\'à '.config('prospection.mail.test_max_recipients').' adresses valides, séparées par des virgules.']);
        }

        $results = $campaigns->sendTest(MailAccount::findOrFail($data['mail_account_id']), $this->version($request, $template), $emails, $template->category !== 'operationnel', $request->user());
        $failed = array_filter($results, fn ($r) => ! $r->isAccepted());

        return $failed
            ? back()->withErrors(['emails' => 'Échec pour '.implode(', ', array_keys($failed)).' : '.reset($failed)->error])
            : back()->with('success', 'E-mail de test accepté par le serveur d\'envoi pour '.implode(', ', $emails).'.');
    }

    public function destroy(Request $request, Template $template): RedirectResponse
    {
        $this->guard($request);
        if (Campaign::where('template_id', $template->id)->whereIn('status', array_merge(Campaign::EDITABLE, Campaign::ACTIVE))->exists()) {
            return back()->withErrors(['template' => 'Ce modèle est utilisé par une campagne en préparation ou en cours.']);
        }
        $template->delete();
        $this->audit->log('template.deleted', $template, ['nom' => $template->name]);

        return redirect()->route('pm.templates.index')->with('success', 'Modèle archivé.');
    }

    /** @return array<int, string>|null */
    public function parseEmails(string $value): ?array
    {
        $emails = array_values(array_unique(array_filter(array_map('trim', preg_split('/[,;\s]+/', mb_strtolower($value))))));
        if (! $emails || count($emails) > config('prospection.mail.test_max_recipients')) {
            return null;
        }
        foreach ($emails as $email) {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return null;
            }
        }

        return $emails;
    }

    private function version(Request $request, Template $template): TemplateVersion
    {
        return $request->filled('version')
            ? $template->versions()->where('version', $request->integer('version'))->firstOrFail()
            : $template->latestVersion ?? abort(404);
    }

    /** Retire scripts, formulaires et gestionnaires d'événements du contenu des e-mails. */
    private function sanitize(string $html): string
    {
        $html = preg_replace('#<(script|iframe|object|embed|form|input|button|textarea|select)\b[^>]*>.*?</\1>#is', '', $html);
        $html = preg_replace('#<(script|iframe|object|embed|form|input|button|textarea|select|meta|link|base)\b[^>]*/?>#is', '', $html);
        $html = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);

        return preg_replace('/(href|src)\s*=\s*(["\'])\s*javascript:[^"\']*\2/i', '$1=$2#$2', $html);
    }

    private function guard(Request $request, string $permission = 'templates.manage'): void
    {
        abort_unless($request->user()->can($permission), 403);
    }
}
