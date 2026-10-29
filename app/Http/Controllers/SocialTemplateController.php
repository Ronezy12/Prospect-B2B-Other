<?php

namespace App\Http\Controllers;

use App\Models\SocialTemplate;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Modèles de messages courts à copier-coller sur la plateforme (texte brut, variables). */
class SocialTemplateController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $this->guard($request);

        return view('social.templates.index', ['templates' => SocialTemplate::orderByDesc('is_active')->orderBy('kind')->orderBy('name')->get()]);
    }

    public function create(Request $request): View
    {
        $this->guard($request);

        return view('social.templates.form', ['template' => new SocialTemplate(['kind' => 'message', 'is_active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->guard($request);
        $template = new SocialTemplate($this->validated($request));
        $template->created_by = $request->user()->id;
        $template->save();
        $this->audit->log('social_template.created', $template, ['nom' => $template->name]);

        return redirect()->route('pm.social.templates.index')->with('success', 'Modèle enregistré.');
    }

    public function edit(Request $request, SocialTemplate $template): View
    {
        $this->guard($request);

        return view('social.templates.form', ['template' => $template]);
    }

    public function update(Request $request, SocialTemplate $template): RedirectResponse
    {
        $this->guard($request);
        $template->fill($this->validated($request))->save();
        $this->audit->log('social_template.updated', $template, $this->audit->diff($template));

        return redirect()->route('pm.social.templates.index')->with('success', 'Modèle enregistré.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'platform' => ['nullable', Rule::in(array_keys(config('prospection.social.platforms')))],
            'kind' => ['required', Rule::in(array_keys(config('prospection.social.template_kinds')))],
            'body' => ['required', 'string', 'max:3000'],
            'is_active' => ['nullable', 'boolean'],
        ], [], ['kind' => 'usage', 'body' => 'texte', 'platform' => 'réseau']);

        preg_match_all('/\{\{\s*([a-z_]+)\s*\}\}/i', $data['body'], $m);
        $unknown = array_diff(array_map('strtolower', $m[1]), config('prospection.social.template_variables'));
        if ($unknown) {
            throw ValidationException::withMessages(['body' => 'Variable(s) inconnue(s) : '.implode(', ', array_map(fn ($v) => '{{'.$v.'}}', $unknown)).'.']);
        }
        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        $data['platform'] = $data['platform'] ?? null;

        return $data;
    }

    private function guard(Request $request): void
    {
        abort_unless($request->user()->can('social.templates'), 403);
    }
}
