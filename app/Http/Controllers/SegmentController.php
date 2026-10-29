<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Sector;
use App\Models\Segment;
use App\Models\Source;
use App\Models\Tag;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Mail\AudienceBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SegmentController extends Controller
{
    public function __construct(private readonly AuditLogger $audit, private readonly AudienceBuilder $audience) {}

    public function index(Request $request): View
    {
        $this->guard($request, 'campaigns.view');

        return view('segments.index', ['segments' => Segment::latest('updated_at')->paginate(config('prospection.per_page'))]);
    }

    public function create(Request $request): View
    {
        $this->guard($request);

        return view('segments.form', ['segment' => new Segment(['filters' => ['lifecycle' => 'prospect']]), 'estimate' => null] + $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $this->guard($request);
        $segment = Segment::create($this->validated($request));
        $segment->forceFill(['created_by' => $request->user()->id])->save();
        $this->audit->log('segment.created', $segment, ['filtres' => $segment->filters]);

        return redirect()->route('pm.segments.edit', $segment)->with('success', 'Segment enregistré.');
    }

    public function edit(Request $request, Segment $segment): View
    {
        $this->guard($request);
        $probe = new Campaign(['type' => 'prospection']);
        $probe->setRelation('segment', $segment);

        return view('segments.form', ['segment' => $segment, 'estimate' => $this->audience->estimate($probe, $request->user())] + $this->formData());
    }

    public function update(Request $request, Segment $segment): RedirectResponse
    {
        $this->guard($request);
        $segment->update($this->validated($request));
        $this->audit->log('segment.updated', $segment, ['filtres' => $segment->filters]);

        return redirect()->route('pm.segments.edit', $segment)->with('success', 'Segment enregistré.');
    }

    public function destroy(Request $request, Segment $segment): RedirectResponse
    {
        $this->guard($request);
        $segment->delete();
        $this->audit->log('segment.deleted', $segment);

        return redirect()->route('pm.segments.index')->with('success', 'Segment supprimé.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'filters.sector_id' => ['nullable', 'exists:pm_sectors,id'],
            'filters.status' => ['nullable', Rule::in(array_keys(config('prospection.company_statuses')))],
            'filters.source_id' => ['nullable', 'exists:pm_sources,id'],
            'filters.owner_id' => ['nullable', 'exists:users,id'],
            'filters.city' => ['nullable', 'string', 'max:120'],
            'filters.postal_code' => ['nullable', 'string', 'max:12'],
            'filters.tag_id' => ['nullable', 'exists:pm_tags,id'],
            'filters.lifecycle' => ['required', Rule::in(['prospect', 'client', 'all'])],
            'filters.audience_type' => ['nullable', Rule::in(array_keys(config('prospection.audience_types')))],
            'filters.legal_basis' => ['nullable', Rule::in(array_keys(config('prospection.legal_bases')))],
        ], [], ['name' => 'nom du segment']);

        return ['name' => $data['name'], 'filters' => array_filter($data['filters'] ?? [], fn ($v) => filled($v))];
    }

    private function formData(): array
    {
        return [
            'sectors' => Sector::roots()->with('children')->get(),
            'sources' => Source::orderBy('name')->get(),
            'tags' => Tag::orderBy('name')->get(),
            'users' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ];
    }

    private function guard(Request $request, string $permission = 'campaigns.manage'): void
    {
        abort_unless($request->user()->can($permission), 403);
    }
}
