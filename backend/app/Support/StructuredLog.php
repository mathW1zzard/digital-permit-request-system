<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

final class StructuredLog
{
    /** Keys whose values must never reach a log file. */
    private const SECRET_KEYS = ['password', 'token', 'authorization', 'api_key', 'secret'];

    public static function event(string $event, array $context = [], string $level = 'info'): void
    {
        $context['correlation_id'] ??= self::correlationId();
        foreach (array_keys($context) as $key) {
            if (in_array(strtolower((string) $key), self::SECRET_KEYS, true)) {
                $context[$key] = '[redacted]';
            }
        }
        Log::channel('json')->log($level, $event, $context);
    }

    public static function correlationId(): ?string
    {
        return app()->bound('correlation_id') ? (string) app('correlation_id') : null;
    }
}
