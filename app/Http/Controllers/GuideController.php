<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

/** Guide de prise en main : vocabulaire simple et pas-à-pas pour les nouveaux utilisateurs. */
class GuideController extends Controller
{
    public function __invoke(Request $request): View
    {
        abort_unless($request->user()->can('prospects.view'), 403);

        return view('guide.index');
    }
}
