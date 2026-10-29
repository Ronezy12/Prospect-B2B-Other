<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\SocialAccount;
use App\Models\SocialConversation;
use App\Models\SocialMessage;
use App\Services\ActivityRecorder;
use App\Services\AuditLogger;
use App\Services\Social\MetaClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Messages reçus sur la Page via l'API officielle. On ne peut écrire qu'aux personnes
 * qui ont contacté la Page, dans la fenêtre de réponse autorisée par Meta.
 */
class SocialInboxController extends Controller
{
    public function __construct(private readonly AuditLogger $audit, private readonly ActivityRecorder $activities) {}

    public function index(Request $request): View
    {
        $this->guard($request);
        $status = $request->query('statut') === 'archivee' ? 'archivee' : 'ouverte';

        return view('social.inbox.index', [
            'conversations' => SocialConversation::visibleTo($request->user())->where('status', $status)
                ->with(['company:id,name', 'account:id,name'])
                ->withCount('messages')
                ->orderByDesc('last_message_at')->paginate(config('prospection.per_page'))->withQueryString(),
            'status' => $status,
            'hasAccount' => SocialAccount::exists(),
        ]);
    }

    public function show(Request $request, SocialConversation $conversation): View
    {
        $this->authorizeConversation($request, $conversation);
        if ($conversation->unread) {
            $conversation->forceFill(['unread' => false])->save();
        }
        $conversation->load(['messages.user:id,name', 'company', 'profile', 'account']);

        return view('social.inbox.show', [
            'conversation' => $conversation,
            'companies' => Company::visibleTo($request->user())->orderBy('name')->limit(500)->get(['id', 'name']),
            'profiles' => $conversation->company ? $conversation->company->socialProfiles()->whereIn('platform', ['facebook', 'instagram'])->get() : collect(),
        ]);
    }

    /** Rattachement manuel à un prospect : aucun rapprochement automatique d'identité. */
    public function link(Request $request, SocialConversation $conversation): RedirectResponse
    {
        $this->authorizeConversation($request, $conversation);
        $data = $request->validate([
            'participant_label' => ['nullable', 'string', 'max:150'],
            'company_id' => ['nullable', 'integer'],
            'profile_id' => ['nullable', 'integer'],
        ], [], ['participant_label' => 'nom affiché', 'company_id' => 'prospect']);

        $company = isset($data['company_id']) ? Company::visibleTo($request->user())->findOrFail($data['company_id']) : null;
        $profile = $company && isset($data['profile_id']) ? $company->socialProfiles()->findOrFail($data['profile_id']) : null;
        $conversation->forceFill([
            'participant_label' => $data['participant_label'] ?? null,
            'company_id' => $company?->id,
            'profile_id' => $profile?->id,
        ])->save();
        if ($company && $conversation->wasChanged('company_id')) {
            $this->activities->record($company, 'reseau_social', "Conversation {$conversation->channelLabel()} rattachée à cette fiche.", ['social_conversation_id' => $conversation->id]);
        }
        $this->audit->log('social_conversation.linked', $conversation, ['entreprise' => $company?->id, 'profil' => $profile?->id]);

        return back()->with('success', 'Conversation mise à jour.');
    }

    public function reply(Request $request, SocialConversation $conversation, MetaClient $meta): RedirectResponse
    {
        $this->authorizeConversation($request, $conversation);
        $text = $request->validate(['body' => ['required', 'string', 'max:2000']], [], ['body' => 'message'])['body'];

        if (! $conversation->canReply()) {
            return back()->withInput()->withErrors(['body' => 'La fenêtre de réponse est fermée : la personne doit écrire de nouveau à la Page avant que vous puissiez lui répondre d\'ici.']);
        }
        if ($conversation->profile?->isOpposed()) {
            return back()->withInput()->withErrors(['body' => 'Ce prospect a demandé à ne plus être contacté sur ce réseau.']);
        }
        if (! $conversation->account?->is_active) {
            return back()->withInput()->withErrors(['body' => 'La connexion Meta est désactivée.']);
        }
        $key = 'social-reply:'.$conversation->account_id;
        if (RateLimiter::tooManyAttempts($key, 60)) {
            return back()->withInput()->withErrors(['body' => 'Trop de messages envoyés en peu de temps : réessayez dans une minute.']);
        }
        RateLimiter::hit($key, 60);

        $result = $meta->reply($conversation, $text);
        $message = new SocialMessage([
            'direction' => 'sortant',
            'external_id' => $result['id'] ?? null,
            'body' => $text,
            'status' => $result['ok'] ? 'accepte' : 'echec',
            'error' => $result['error'] ?? null,
            'sent_at' => now(),
        ]);
        $message->conversation_id = $conversation->id;
        $message->user_id = $request->user()->id;
        $message->save();
        $this->audit->log('social_message.sent', $conversation, ['résultat' => $message->status]);

        if (! $result['ok']) {
            return back()->withInput()->withErrors(['body' => $result['error']]);
        }
        $conversation->forceFill(['last_message_at' => now()])->save();
        if ($conversation->company) {
            $this->activities->record($conversation->company, 'reseau_social', "{$conversation->channelLabel()} — réponse envoyée : ".mb_substr($text, 0, 300), ['social_conversation_id' => $conversation->id]);
        }

        return back()->with('success', 'Message accepté par Meta (la remise et la lecture ne sont pas garanties).');
    }

    public function archive(Request $request, SocialConversation $conversation): RedirectResponse
    {
        $this->authorizeConversation($request, $conversation);
        $data = $request->validate(['status' => ['required', Rule::in(['ouverte', 'archivee'])]]);
        $conversation->forceFill(['status' => $data['status'], 'unread' => false])->save();

        return redirect()->route('pm.social.inbox.index')->with('success', $data['status'] === 'archivee' ? 'Conversation archivée.' : 'Conversation rouverte.');
    }

    private function authorizeConversation(Request $request, SocialConversation $conversation): void
    {
        $this->guard($request);
        abort_unless($conversation->isVisibleTo($request->user()), 403);
    }

    private function guard(Request $request): void
    {
        abort_unless($request->user()->can('social.inbox'), 403);
    }
}
