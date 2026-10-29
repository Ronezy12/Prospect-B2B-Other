<?php

namespace App\Console\Commands;

use App\Models\Inbox;
use App\Services\Replies\ReplyDetector;
use Illuminate\Console\Command;

class FetchReplies extends Command
{
    protected $signature = 'pm:replies-fetch';

    protected $description = 'Consulte les boîtes IMAP configurées et enregistre les réponses aux e-mails envoyés';

    public function handle(ReplyDetector $detector): int
    {
        Inbox::where('is_active', true)->get()->each(function (Inbox $inbox) use ($detector) {
            $result = $detector->check($inbox);
            $this->info("{$inbox->name} : {$result['lus']} message(s), {$result['reponses']} réponse(s).");
        });

        return self::SUCCESS;
    }
}
