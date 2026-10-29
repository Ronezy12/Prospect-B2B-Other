<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Contact;
use App\Models\MailAccount;
use App\Services\Mail\CampaignService;
use App\Services\Mail\ComplianceGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class IndividualEmailController extends Controller
{
    public function create(Request $request, Contact $contact, ComplianceGate $gate): View
    {
        $this->guard($request, $contact);
        $probe = new Campaign(['type' => 'individuel']);

        return view('emails.individual', [
            'contact' => $contact->load('company'),
            'accounts' => MailAccount::where('is_active', true)->where('last_test_result', 'ok')->orderBy('name')->get(),
            'blocked' => $gate->check($probe, $contact),
        ]);
    }

    public function store(Request $request, Contact $contact, CampaignService $campaigns, ComplianceGate $gate): RedirectResponse
    {
        $this->guard($request, $contact);
        $data = $request->validate([
            'mail_account_id' => ['required', Rule::exists('pm_mail_accounts', 'id')->where('is_active', true)->where('last_test_result', 'ok')],
            'subject' => ['required', 'string', 'max:190'],
            'body' => ['required', 'string', 'max:20000'],
        ], [], ['mail_account_id' => 'compte d\'envoi', 'subject' => 'objet', 'body' => 'message']);

        if ($reason = $gate->check(new Campaign(['type' => 'individuel']), $contact)) {
            return back()->withInput()->withErrors(['body' => 'Envoi impossible : '.(config('prospection.exclusion_reasons')[$reason] ?? $reason).'.']);
        }

        $campaign = $campaigns->sendIndividual($contact, MailAccount::findOrFail($data['mail_account_id']), $data['subject'], $data['body'], $request->user());
        $status = $campaign->recipients()->value('status');

        return redirect()->route('pm.companies.show', $contact->company_id)->with('success', match ($status) {
            'accepte' => 'E-mail accepté par le serveur d\'envoi.',
            'echec' => 'L\'envoi a échoué : consultez le détail dans Campagnes › E-mails individuels.',
            default => 'E-mail en file d\'attente : il partira dans quelques instants.',
        });
    }

    private function guard(Request $request, Contact $contact): void
    {
        abort_unless($request->user()->can('emails.individual') && $contact->isVisibleTo($request->user()), 403);
    }
}
