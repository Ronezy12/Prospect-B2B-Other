<?php

namespace App\Http\Controllers;

use App\Models\SocialAccount;
use App\Services\Social\MetaWebhookProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Webhook officiel Meta (Messenger / Instagram).
 *  - GET : vérification de l'abonnement (hub.verify_token).
 *  - POST : événements signés (en-tête X-Hub-Signature-256, HMAC SHA-256 du corps brut
 *    avec le secret de l'application) ; toute requête non signée est rejetée.
 */
class MetaWebhookController extends Controller
{
    public function verify(Request $request, string $token): Response
    {
        $account = $this->account($token);
        $challenge = (string) $request->query('hub_challenge', '');
        if (! $account || $request->query('hub_mode') !== 'subscribe'
            || ! hash_equals((string) $account->setting('verify_token'), (string) $request->query('hub_verify_token', ''))
            || ! preg_match('/^[A-Za-z0-9_-]{1,128}$/', $challenge)) {
            return response('Forbidden', 403)->header('Content-Type', 'text/plain');
        }

        return response($challenge, 200)->header('Content-Type', 'text/plain');
    }

    public function receive(Request $request, string $token, MetaWebhookProcessor $processor): JsonResponse
    {
        $account = $this->account($token);
        if (! $account) {
            return response()->json(['error' => 'unauthorized'], 401);
        }
        $raw = $request->getContent();
        $expected = 'sha256='.hash_hmac('sha256', $raw, (string) $account->setting('app_secret'));
        if (! filled($account->setting('app_secret')) || ! hash_equals($expected, (string) $request->header('X-Hub-Signature-256'))) {
            Log::warning('Webhook Meta refusé : signature invalide', ['ip' => $request->ip(), 'compte' => $account->id]);

            return response()->json(['error' => 'invalid signature'], 403);
        }

        $payload = json_decode($raw, true);
        if (! is_array($payload)) {
            return response()->json(['error' => 'invalid payload'], 400);
        }

        return response()->json($processor->handle($account, $payload));
    }

    private function account(string $token): ?SocialAccount
    {
        if (strlen($token) !== 48) {
            return null;
        }

        return SocialAccount::where('is_active', true)->get()->first(fn (SocialAccount $a) => hash_equals($a->webhook_token, $token));
    }
}
