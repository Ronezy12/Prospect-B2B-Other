<?php

namespace App\Services\Replies;

use App\Models\Inbox;

interface InboxReader
{
    /**
     * Messages reçus depuis le dernier UID traité (en-têtes seulement, sans les corps).
     *
     * @return iterable<IncomingMessage>
     */
    public function fetch(Inbox $inbox, int $afterUid, int $limit): iterable;
}
