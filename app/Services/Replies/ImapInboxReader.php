<?php

namespace App\Services\Replies;

use App\Models\Inbox;
use Carbon\Carbon;
use Webklex\PHPIMAP\ClientManager;

/**
 * Lecture IMAP (webklex/php-imap, MIT). Seuls les en-têtes sont lus : le contenu des
 * réponses n'est ni téléchargé ni stocké. Les messages ne sont pas marqués comme lus.
 */
class ImapInboxReader implements InboxReader
{
    public function fetch(Inbox $inbox, int $afterUid, int $limit): iterable
    {
        $client = (new ClientManager)->make([
            'host' => $inbox->setting('host'),
            'port' => (int) $inbox->setting('port', 993),
            'encryption' => $inbox->setting('encryption', 'ssl') === 'none' ? false : $inbox->setting('encryption', 'ssl'),
            'validate_cert' => true,
            'username' => $inbox->setting('username'),
            'password' => $inbox->setting('password'),
            'protocol' => 'imap',
            'timeout' => 30,
        ]);
        $client->connect();

        try {
            $messages = $client->getFolderByPath($inbox->folder)->query()
                ->setFetchBody(false)->leaveUnread()->limit($limit)
                ->getByUidGreater($afterUid);

            foreach ($messages as $message) {
                $from = $message->getFrom()->first();
                $references = preg_split('/\s+/', trim((string) $message->getReferences()->toString())) ?: [];
                yield new IncomingMessage(
                    uid: (int) $message->getUid(),
                    from: mb_strtolower((string) ($from->mail ?? '')),
                    subject: mb_substr((string) $message->getSubject()->toString(), 0, 190),
                    inReplyTo: trim((string) $message->getInReplyTo()->toString()) ?: null,
                    references: array_values(array_filter($references)),
                    date: Carbon::parse((string) $message->getDate()->toString() ?: 'now'),
                );
            }
        } finally {
            $client->disconnect();
        }
    }
}
