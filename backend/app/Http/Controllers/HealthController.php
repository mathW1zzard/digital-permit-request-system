<?php

namespace App\Http\Controllers;

use App\Models\OutboxNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Week 16: readiness signal for monitoring.
 *
 * Liveness is Laravel's /up (the process answers). Readiness answers a different
 * question: can this instance serve permit requests right now, and is the
 * background automation keeping up?
 *
 *  - database   required. If it fails the instance is not ready (503).
 *  - scheduler  the automation and dispatch commands write a heartbeat each run.
 *               A stale heartbeat means "degraded": requests still work, but
 *               reminders, escalations and notifications have stopped.
 *  - outbox     notifications overdue for delivery, or waiting for a person.
 *               The gateway is not called from here: delivery is asynchronous,
 *               so a gateway outage shows up as an overdue backlog instead.
 *
 * The response holds statuses, ages and counts only: no hostnames, paths,
 * versions or error messages.
 */
class HealthController extends Controller
{
    public const HEARTBEATS = ['automation' => 'health.heartbeat.automation', 'dispatch' => 'health.heartbeat.dispatch'];

    public function ready(): JsonResponse
    {
        $checks = [];
        $ready = true;
        $degraded = false;

        try {
            DB::select('select 1');
            $checks['database'] = ['status' => 'ok'];
        } catch (Throwable) {
            $checks['database'] = ['status' => 'fail'];
            $ready = false;
        }

        if ($ready) {
            $maxAge = (int) config('permit.health.heartbeat_max_age_seconds');
            foreach (self::HEARTBEATS as $name => $key) {
                $last = $this->safe(fn () => Cache::get($key));
                $age = $last ? (int) Carbon::parse($last)->diffInSeconds(now(), true) : null;
                $ok = $age !== null && $age <= $maxAge;
                $checks["scheduler_{$name}"] = ['status' => $ok ? 'ok' : 'stale', 'age_seconds' => $age];
                $degraded = $degraded || ! $ok;
            }

            $overdueAfter = (int) config('permit.health.outbox_overdue_seconds');
            $overdue = (int) $this->safe(fn () => OutboxNotification::where('status', OutboxNotification::PENDING)
                ->where('next_attempt_at', '<', now()->subSeconds($overdueAfter))->count());
            $manual = (int) $this->safe(fn () => OutboxNotification::where('status', OutboxNotification::MANUAL)->count());
            $outboxOk = $overdue === 0;
            $checks['outbox'] = ['status' => $outboxOk ? 'ok' : 'backlog', 'overdue' => $overdue, 'manual_action' => $manual];
            $degraded = $degraded || ! $outboxOk;
        }

        $status = ! $ready ? 'not_ready' : ($degraded ? 'degraded' : 'ready');

        return response()->json([
            'status' => $status,
            'checks' => $checks,
            'checked_at' => now()->toIso8601String(),
        ], $ready ? 200 : 503)->header('Cache-Control', 'no-store');
    }

    public static function beat(string $name): void
    {
        try {
            Cache::forever(self::HEARTBEATS[$name], now()->toIso8601String());
        } catch (Throwable) {
            // A heartbeat must never break the job it reports on.
        }
    }

    private function safe(callable $fn): mixed
    {
        try {
            return $fn();
        } catch (Throwable) {
            return null;
        }
    }
}
