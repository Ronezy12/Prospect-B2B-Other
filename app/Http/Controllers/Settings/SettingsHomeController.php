<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Inbox;
use App\Models\MailAccount;
use App\Models\Pipeline;
use App\Models\Setting;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Page d'entrée des paramètres : chaque réglage, à quoi il sert et s'il est en place. */
class SettingsHomeController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->canAny(['users.manage', 'pipelines.manage', 'settings.manage']), 403);

        $items = [];
        if ($user->can('users.manage')) {
            $items[] = ['icon' => 'users', 'title' => 'Utilisateurs et rôles', 'text' => 'Qui a accès au module et ce que chacun peut faire.', 'route' => 'pm.settings.users.index',
                'state' => User::where('is_active', true)->count().' utilisateur(s) actif(s)', 'ok' => true];
        }
        if ($user->can('pipelines.manage')) {
            $items[] = ['icon' => 'kanban', 'title' => 'Secteurs et pipelines', 'text' => 'Les secteurs ciblés et les étapes de vente de chacun.', 'route' => 'pm.settings.pipelines.index',
                'state' => Pipeline::count().' pipeline(s)', 'ok' => true];
        }
        if ($user->can('settings.manage')) {
            $okAccounts = MailAccount::where('is_active', true)->where('last_test_result', 'ok')->where('driver', '!=', 'log')->count();
            $items[] = ['icon' => 'inbox', 'title' => 'Envoi d\'e-mails', 'text' => 'Le compte SMTP ou Brevo utilisé pour envoyer vos e-mails.', 'route' => 'pm.settings.mail.index',
                'state' => $okAccounts ? $okAccounts.' compte(s) opérationnel(s)' : 'À configurer', 'ok' => $okAccounts > 0];
            $inboxes = Inbox::where('is_active', true)->count();
            $items[] = ['icon' => 'mail', 'title' => 'Réponses (IMAP)', 'text' => 'La boîte mail lue pour savoir qui a répondu.', 'route' => 'pm.settings.inboxes.index',
                'state' => $inboxes ? $inboxes.' boîte(s) consultée(s)' : 'Facultatif — non configuré', 'ok' => $inboxes > 0, 'optional' => true];
            $items[] = ['icon' => 'shield-check', 'title' => 'Suivi des e-mails', 'text' => 'Mesure des ouvertures et des clics, avec consentement.', 'route' => 'pm.settings.tracking.edit',
                'state' => Setting::get('tracking.opens', false) || Setting::get('tracking.clicks', false) ? 'Activé' : 'Désactivé (par défaut)', 'ok' => true, 'optional' => true];
            $meta = SocialAccount::where('is_active', true)->where('last_test_result', 'ok')->count();
            $items[] = ['icon' => 'share-2', 'title' => 'Connexion Meta', 'text' => 'Recevoir les messages de votre Page Facebook / Instagram.', 'route' => 'pm.settings.social.index',
                'state' => $meta ? 'Connectée' : 'Facultatif — non connectée', 'ok' => $meta > 0, 'optional' => true];
        }

        return view('settings.home', ['items' => $items]);
    }
}
