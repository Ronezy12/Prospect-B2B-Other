<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Journal d'audit append-only. Les attributs sensibles sont masqués avant
 * écriture : aucun secret ne doit apparaître dans le journal.
 */
class AuditLogger
{
    private const MASKED = ['password', 'remember_token', 'api_key', 'secret', 'token', 'smtp_password'];

    /** @param array<string, mixed> $changes */
    public function log(string $action, ?Model $subject = null, array $changes = [], ?int $userId = null): AuditLog
    {
        return AuditLog::create([
            'user_id' => $userId ?? Auth::id(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'changes' => $changes ? $this->mask($changes) : null,
            'ip_address' => Request::ip(),
            'user_agent' => mb_substr((string) Request::userAgent(), 0, 255),
        ]);
    }

    /** Différence avant/après d'un modèle venant d'être sauvegardé. */
    public function diff(Model $model): array
    {
        $changes = [];
        $previous = $model->getPrevious();
        foreach ($model->getChanges() as $key => $value) {
            if (in_array($key, ['updated_at', 'name_normalized', 'email_normalized', 'domain', 'siren'], true)) {
                continue;
            }
            $changes[$key] = ['avant' => $previous[$key] ?? null, 'après' => $value];
        }

        return $changes;
    }

    private function mask(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::MASKED, true)) {
                $data[$key] = '***';
            } elseif (is_array($value)) {
                $data[$key] = $this->mask($value);
            }
        }

        return $data;
    }
}
