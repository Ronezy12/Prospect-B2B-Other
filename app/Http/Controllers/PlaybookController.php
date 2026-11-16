<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Sector;
use App\Support\Playbooks;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Fiches secteur : ce qu'on propose, comment qualifier, et les modèles d'e-mails. */
class PlaybookController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('prospects.view'), 403);

        return view('playbooks.index', ['playbooks' => Playbooks::all()]);
    }

    public function show(Request $request, string $playbook): View
    {
        abort_unless($request->user()->can('prospects.view'), 403);
        $item = Playbooks::find($playbook) ?? abort(404);
        $sector = Sector::whereIn('slug', array_map(fn ($n) => Str::slug($n), $item['sectors']))->first();

        return view('playbooks.show', [
            'playbook' => $item,
            'sector' => $sector,
            'prospectCount' => $sector ? Company::visibleTo($request->user())->where('lifecycle', 'prospect')->where('sector_id', $sector->id)->count() : 0,
        ]);
    }
}
