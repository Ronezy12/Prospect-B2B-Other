<?php

namespace App\Services\Social;

use App\Models\SocialAccount;
use App\Models\SocialConversation;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Appels à l'API Graph officielle de Meta. Le jeton d'accès de la Page est transmis en
 * en-tête et n'apparaît jamais dans les journaux ni dans les messages d'erreur affichés.
 */
class MetaClient
{
    /** @return array{ok: bool, name?: string, error?: string} */
    public function test(SocialAccount $account): array
    {
        try {
            $response = $this->http($account)->get($this->url($account, (string) $account->setting('page_id')), ['fields' => 'id,name']);
            if ($response->successful() && $response->json('id')) {
                return ['ok' => true, 'name' => (string) $response->json('name')];
            }

            return ['ok' => false, 'error' => $this->error($response)];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => 'Connexion à Meta impossible : '.class_basename($e).'.'];
        }
    }

    /**
     * Réponse à une personne qui a écrit à la Page (type RESPONSE, dans la fenêtre autorisée).
     * Un identifiant renvoyé signifie que Meta a accepté le message, pas qu'il a été lu.
     *
     * @return array{ok: bool, id?: string, error?: string}
     */
    public function reply(SocialConversation $conversation, string $text): array
    {
        $account = $conversation->account;
        try {
            $response = $this->http($account)->post($this->url($account, (string) $account->setting('page_id').'/messages'), [
                'recipient' => ['id' => $conversation->participant_id],
                'messaging_type' => 'RESPONSE',
                'message' => ['text' => $text],
            ]);
            if ($response->successful() && $response->json('message_id')) {
                return ['ok' => true, 'id' => (string) $response->json('message_id')];
            }

            return ['ok' => false, 'error' => $this->error($response)];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => 'Connexion à Meta impossible : '.class_basename($e).'.'];
        }
    }

    private function http(SocialAccount $account)
    {
        return Http::withToken((string) $account->setting('page_access_token'))->acceptJson()->timeout(15)->connectTimeout(5);
    }

    private function url(SocialAccount $account, string $path): string
    {
        return rtrim(config('prospection.social.meta.graph_url'), '/').'/'.($account->setting('graph_version') ?: config('prospection.social.meta.graph_version')).'/'.ltrim($path, '/');
    }

    private function error(Response $response): string
    {
        $message = (string) ($response->json('error.message') ?? 'réponse inattendue');
        $code = $response->json('error.code');

        return mb_substr('Meta a refusé la demande (HTTP '.$response->status().($code ? ", code {$code}" : '').') : '.$message, 0, 255);
    }
}
