<?php

namespace App\Http\Controllers;

use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Services\Mail\DeliveryTracker;
use App\Services\Mail\TrackingPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Suivi interne (fonctionne aussi avec un simple SMTP). Liens signés par HMAC :
 * aucune redirection ouverte possible. Une ouverture n'est jamais une preuve de lecture
 * (images bloquées, préchargement par certaines messageries…).
 */
class TrackingController extends Controller
{
    private const GIF = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    public static function sign(int $recipientId, string $value = ''): string
    {
        return substr(hash_hmac('sha256', $recipientId.'|'.$value, (string) config('app.key')), 0, 32);
    }

    public function open(CampaignRecipient $recipient, string $signature, DeliveryTracker $tracker, TrackingPolicy $policy): Response
    {
        if (hash_equals(self::sign($recipient->id, 'open'), $signature)) {
            $contact = $recipient->contact_id ? Contact::find($recipient->contact_id) : null;
            if ($policy->allows('opens', $recipient->campaign, $contact)) {
                // Une ouverture par tranche de 10 minutes au plus (rechargements, aperçus).
                $tracker->record($recipient, 'ouverture', now(), 'interne', 'open:'.$recipient->id.':'.intdiv(now()->timestamp, 600));
            }
        }

        return response(base64_decode(self::GIF), 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    public function click(Request $request, CampaignRecipient $recipient, string $signature, DeliveryTracker $tracker, TrackingPolicy $policy): RedirectResponse
    {
        $url = (string) $request->query('u', '');
        abort_unless(preg_match('#^https?://#i', $url) && hash_equals(self::sign($recipient->id, $url), $signature), 404);

        $contact = $recipient->contact_id ? Contact::find($recipient->contact_id) : null;
        if ($policy->allows('clicks', $recipient->campaign, $contact)) {
            $tracker->record($recipient, 'clic', now(), 'interne', 'click:'.$recipient->id.':'.sha1($url).':'.intdiv(now()->timestamp, 60), ['lien' => mb_substr($url, 0, 255)]);
        }

        return redirect()->away($url);
    }
}
