<?php

namespace App\Services;

use App\Enums\PermitStatus;
use App\Exceptions\InvalidTransition;
use App\Models\Permit;
use App\Support\StructuredLog;
use Illuminate\Support\Facades\DB;
use Throwable;

/** The scheduled half of the workflow. Every step is safe to run twice. */
final class PermitAutomation
{
    private const BATCH = 200; // bounded work per run

    public function __construct(
        private readonly PermitWorkflow $workflow,
        private readonly NotificationOutbox $outbox,
    ) {}

    /** @return array<string, int> counts per step; -1 means the step failed */
    public function run(): array
    {
        $steps = [
            'rerouted' => fn () => $this->rerouteUnassigned(),
            'escalated' => fn () => $this->escalateStalled(),
            'reminders' => fn () => $this->sendExpiryReminders(),
            'expired' => fn () => $this->expireLapsed(),
        ];

        $result = [];
        foreach ($steps as $name => $step) {
            try {
                $result[$name] = $step();
            } catch (Throwable $e) {
                $result[$name] = -1; // one broken step must not stop the others
                StructuredLog::event('automation.step_failed', ['step' => $name, 'exception' => $e::class, 'message' => $e->getMessage()], 'error');
            }
        }

        return $result;
    }

    /** Recovery for routing exceptions: try again now that staff may be available. */
    private function rerouteUnassigned(): int
    {
        $count = 0;
        $stuck = Permit::with('type')
            ->where('needs_manual_action', true)
            ->whereIn('manual_action_reason', ['no_reviewer_available', 'no_approver_available'])
            ->limit(self::BATCH)
            ->get();

        foreach ($stuck as $permit) {
            DB::transaction(function () use ($permit) {
                if ($permit->status === PermitStatus::UnderReview) {
                    $this->workflow->routeToReviewer($permit);
                } else {
                    $this->workflow->routeToApprover($permit);
                }
            });
            if (! $permit->needs_manual_action) {
                $count++;
            }
        }

        return $count;
    }

    /** Exception escalation: reviews or approvals that sat longer than their SLA. */
    private function escalateStalled(): int
    {
        $count = 0;
        $open = Permit::with('type')
            ->whereIn('status', [PermitStatus::UnderReview->value, PermitStatus::PendingApproval->value])
            ->where('needs_manual_action', false)
            ->limit(self::BATCH)
            ->get();

        foreach ($open as $permit) {
            $inReview = $permit->status === PermitStatus::UnderReview;
            $slaHours = $inReview ? $permit->type->review_sla_hours : $permit->type->approval_sla_hours;
            if ($permit->stage_entered_at->copy()->addHours($slaHours)->isFuture()) {
                continue;
            }
            $this->workflow->flagManual($permit, $inReview ? 'review_sla_breached' : 'approval_sla_breached');
            $count++;
        }

        return $count;
    }

    /** One reminder per threshold (type's reminder_days, then 1 day), deduplicated by key. */
    private function sendExpiryReminders(): int
    {
        $count = 0;
        $issued = Permit::with(['type', 'requester'])
            ->where('status', PermitStatus::Issued->value)
            ->where('valid_until', '>', now())
            ->limit(self::BATCH)
            ->get();

        foreach ($issued as $permit) {
            $secondsLeft = $permit->valid_until->getTimestamp() - now()->getTimestamp();
            $thresholds = collect([1, $permit->type->reminder_days])->filter(fn ($d) => $d > 0)->unique()->sort()->values();

            foreach ($thresholds as $days) {
                if ($secondsLeft <= $days * 86400) {
                    if ($this->outbox->enqueue($permit, $permit->requester, 'expiry_reminder', "expiry_reminder:{$permit->id}:{$days}d")) {
                        $count++;
                    }
                    break; // only the closest threshold
                }
            }
        }

        return $count;
    }

    /** Automatic status expiration. */
    private function expireLapsed(): int
    {
        $count = 0;
        $lapsed = Permit::where('status', PermitStatus::Issued->value)
            ->where('valid_until', '<=', now())
            ->limit(self::BATCH)
            ->get();

        foreach ($lapsed as $permit) {
            try {
                $this->workflow->markExpired($permit);
                $count++;
            } catch (InvalidTransition) {
                // another run expired it a moment earlier: nothing to do
            }
        }

        return $count;
    }
}
