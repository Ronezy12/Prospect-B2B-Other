<?php

namespace App\Services\Replies;

use Carbon\CarbonInterface;

final class IncomingMessage
{
    /** @param array<int, string> $references */
    public function __construct(
        public readonly int $uid,
        public readonly string $from,
        public readonly string $subject,
        public readonly ?string $inReplyTo,
        public readonly array $references,
        public readonly CarbonInterface $date,
    ) {}
}
