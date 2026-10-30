<?php

namespace App\Services;

use App\Models\OutboxNotification;
use App\Support\StructuredLog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class NotificationDispatcher
{
    /** HTTP statuses worth retrying. Every other non-2xx status is permanent. */
    private const TRANSIENT_HTTP = [408, 425, 429, 500, 502, 503, 504];

    /** Stop a run after this many transient failures in a row (back-pressure). */
    private const PAUSE_AFTER_TRANSIENT_IN_A_ROW = 3;

    /** @return array<string, int> */
    public function dispatchDue(int $limit = 50): array
    {
        $stats = ['sent' => 0, 'retry_scheduled' => 0, 'manual_action' => 0, 'skipped' => 0, 'paused' => 0];
        $transientInARow = 0;

        $due = OutboxNotification::with(['permit', 'recipient'])
            ->where('status', OutboxNotification::PENDING)
            ->where('next_attempt_at', '<=', now())
            ->orderBy('next_attempt_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($due as $notification) {
            if ($transientInARow >= self::PAUSE_AFTER_TRANSIENT_IN_A_ROW) {
                $stats['paused'] = 1;
                StructuredLog::event('dispatch.paused', ['reason' => 'gateway_unhealthy', 'transient_in_a_row' => $transientInARow], 'warning');
                break;
            }
            if (! $this->claim($notification)) {
                $stats['skipped']++; // another dispatcher took it

                continue;
            }
            [$outcome, $failureClass] = $this->attempt($notification);
            $stats[$outcome]++;
            $transientInARow = $failureClass === 'transient' ? $transientInARow + 1 : 0;
        }

        return $stats;
    }

    /** Lease the row: count the attempt and push next_attempt_at forward before calling out. */
    private function claim(OutboxNotification $n): bool
    {
        $lease = now()->addSeconds(config('permit.gateway.timeout') * 2 + 5);

        $claimed = OutboxNotification::whereKey($n->id)
            ->where('status', OutboxNotification::PENDING)
            ->where('attempts', $n->attempts)
            ->update(['attempts' => $n->attempts + 1, 'next_attempt_at' => $lease, 'updated_at' => now()]) === 1;

        if ($claimed) {
            $n->attempts++;
        }

        return $claimed;
    }

    /** @return array{0: string, 1: ?string} outcome and failure class */
    private function attempt(OutboxNotification $n): array
    {
        $cfg = config('permit.gateway');
        $started = microtime(true);

        try {
            $response = Http::withToken($cfg['api_key'])
                ->withHeaders([
                    'Idempotency-Key' => $n->dedupe_key, // same key on every retry: the gateway delivers once
                    'X-Correlation-Id' => $n->correlation_id ?? (string) Str::uuid(),
                ])
                ->acceptJson()
                ->timeout($cfg['timeout'])
                ->post($cfg['url'], [
                    'to' => $n->recipient->email,
                    'template' => $n->kind,
                    'reference' => $n->permit->reference,
                ]);
            $status = $response->status();
        } catch (ConnectionException) {
            $status = 0; // timeout, refused connection or DNS failure
        }

        $context = [
            'notification_id' => $n->id,
            'permit' => $n->permit->reference,
            'kind' => $n->kind,
            'attempt' => $n->attempts,
            'max_attempts' => $cfg['max_attempts'],
            'gateway_status' => $status,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'correlation_id' => $n->correlation_id,
        ];

        if ($status >= 200 && $status < 300) {
            $n->update(['status' => OutboxNotification::SENT, 'sent_at' => now(), 'last_error_class' => null, 'last_error_detail' => null]);
            StructuredLog::event('notification.sent', $context + ['recovered_after_retry' => $n->attempts > 1]);

            return ['sent', null];
        }

        $detail = $status === 0 ? 'gateway_unreachable' : "gateway_http_{$status}";

        if ($status !== 0 && ! in_array($status, self::TRANSIENT_HTTP, true)) {
            return $this->handOverToPerson($n, 'permanent', $detail, $context, 'permanent');
        }

        if ($n->attempts >= $cfg['max_attempts']) {
            return $this->handOverToPerson($n, 'transient_exhausted', $detail, $context, 'transient');
        }

        $delay = $this->backoffSeconds($n->attempts);
        $n->update(['next_attempt_at' => now()->addSeconds($delay), 'last_error_class' => 'transient', 'last_error_detail' => $detail]);
        StructuredLog::event('notification.retry_scheduled', $context + ['retry_in_seconds' => $delay, 'error' => $detail], 'warning');

        return ['retry_scheduled', 'transient'];
    }

    private function handOverToPerson(OutboxNotification $n, string $class, string $detail, array $context, string $failureClass): array
    {
        $n->update(['status' => OutboxNotification::MANUAL, 'last_error_class' => $class, 'last_error_detail' => $detail]);
        StructuredLog::event('notification.manual_action_required', $context + ['failure_class' => $class, 'error' => $detail], 'error');

        return ['manual_action', $failureClass];
    }

    /** Exponential backoff with a cap and +/-20% jitter, so retries do not arrive in lockstep. */
    private function backoffSeconds(int $attempt): int
    {
        $cfg = config('permit.gateway');
        $delay = min($cfg['backoff_cap'], $cfg['backoff_base'] * (2 ** ($attempt - 1)));

        return max(1, (int) round($delay * random_int(80, 120) / 100));
    }
}
