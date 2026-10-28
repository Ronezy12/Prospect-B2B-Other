<?php

namespace App\Services;

use App\Models\Activity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/** Historique métier visible sur les fiches (distinct du journal d'audit). */
class ActivityRecorder
{
    public function record(Model $subject, string $type, string $body, array $meta = [], ?int $userId = null): Activity
    {
        return Activity::create([
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'user_id' => $userId ?? Auth::id(),
            'type' => $type,
            'body' => $body,
            'meta' => $meta ?: null,
            'occurred_at' => now(),
        ]);
    }
}
