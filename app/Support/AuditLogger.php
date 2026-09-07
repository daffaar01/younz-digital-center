<?php

namespace App\Support;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AuditLogger
{
    /** @param array<string, mixed>|null $before @param array<string, mixed>|null $after */
    public function log(string $action, ?Model $subject = null, ?array $before = null, ?array $after = null, array $metadata = []): ActivityLog
    {
        return ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'before' => $this->redact($before),
            'after' => $this->redact($after),
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'metadata' => $this->redact($metadata ?: null),
        ]);
    }

    public function identifierFingerprint(string $identifier): string
    {
        return hash_hmac(
            'sha256',
            Str::lower(trim($identifier)),
            (string) config('app.key'),
        );
    }

    /** @param array<string, mixed>|null $values @return array<string, mixed>|null */
    private function redact(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        foreach ($values as $key => $value) {
            if (preg_match('/(?:password|secret|token|api[_-]?key|authorization|cookie|card[_-]?number|cvv)/i', (string) $key)) {
                $values[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $values[$key] = $this->redact($value);
            }
        }

        return $values;
    }
}
