<?php

namespace App\Services;

use App\Enums\PermitStatus;
use App\Exceptions\InvalidTransition;
use App\Models\Permit;
use App\Models\PermitEvent;
use App\Models\PermitType;
use App\Models\User;
use App\Support\StructuredLog;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PermitWorkflow
{
    public function __construct(
        private readonly ApprovalRouter $router,
        private readonly NotificationOutbox $outbox,
    ) {}

    public function submit(User $requester, PermitType $type, array $data): Permit
    {
        return DB::transaction(function () use ($requester, $type, $data) {
            $permit = Permit::create([
                'reference' => 'PRM-'.now()->format('Ymd').'-'.Str::upper(Str::random(5)),
                'requester_id' => $requester->id,
                'permit_type_id' => $type->id,
                'purpose' => $data['purpose'],
                'location' => $data['location'],
                'details' => $data['details'] ?? null,
                'requested_start' => $data['requested_start'],
                'status' => PermitStatus::UnderReview,
                'revision_count' => 0, // set explicitly: the dedupe key below reads it before any refresh
                'submitted_at' => now(),
                'stage_entered_at' => now(),
            ]);
            $this->event($permit, $requester, 'submitted', [], null, PermitStatus::UnderReview);
            $this->routeToReviewer($permit);

            return $permit;
        });
    }

    public function requestRevision(Permit $permit, User $reviewer, string $comment): void
    {
        DB::transaction(function () use ($permit, $reviewer, $comment) {
            $this->move($permit, PermitStatus::RevisionRequested, $reviewer, 'revision_requested', ['comment' => $comment]);
            $this->outbox->enqueue($permit, $permit->requester, 'revision_requested', "revision_requested:{$permit->id}:{$permit->revision_count}");
        });
    }

    public function resubmit(Permit $permit, User $requester, array $changes): void
    {
        DB::transaction(function () use ($permit, $requester, $changes) {
            $this->move($permit, PermitStatus::UnderReview, $requester, 'resubmitted');
            $permit->update(Arr::only($changes, ['purpose', 'details']) + ['revision_count' => $permit->revision_count + 1]);
            if ($permit->reviewer_id) {
                $this->outbox->enqueue($permit, $permit->reviewer, 'review_assigned', "review_assigned:{$permit->id}:{$permit->revision_count}");
            } else {
                $this->routeToReviewer($permit);
            }
        });
    }

    public function endorse(Permit $permit, User $reviewer): void
    {
        DB::transaction(function () use ($permit, $reviewer) {
            $this->move($permit, PermitStatus::PendingApproval, $reviewer, 'endorsed');
            $this->routeToApprover($permit);
        });
    }

    public function approve(Permit $permit, User $approver): void
    {
        DB::transaction(function () use ($permit, $approver) {
            $this->move($permit, PermitStatus::Issued, $approver, 'approved');
            $permit->update([
                'issued_at' => now(),
                'valid_from' => now(),
                'valid_until' => now()->addDays($permit->type->validity_days),
            ]);
            $this->outbox->enqueue($permit, $permit->requester, 'permit_issued', "permit_issued:{$permit->id}");
        });
    }

    public function reject(Permit $permit, User $actor, string $reason): void
    {
        DB::transaction(function () use ($permit, $actor, $reason) {
            $this->move($permit, PermitStatus::Rejected, $actor, 'rejected', ['reason' => $reason]);
            $this->outbox->enqueue($permit, $permit->requester, 'permit_rejected', "permit_rejected:{$permit->id}");
        });
    }

    public function markExpired(Permit $permit): void
    {
        DB::transaction(function () use ($permit) {
            $this->move($permit, PermitStatus::Expired, null, 'expired');
            $this->outbox->enqueue($permit, $permit->requester, 'permit_expired', "permit_expired:{$permit->id}");
        });
    }

    public function routeToReviewer(Permit $permit): void
    {
        $reviewer = $this->router->pickReviewer();
        if (! $reviewer) {
            $this->flagManual($permit, 'no_reviewer_available');

            return;
        }
        $permit->update(['reviewer_id' => $reviewer->id, 'needs_manual_action' => false, 'manual_action_reason' => null]);
        $this->event($permit, null, 'routed_to_reviewer', ['reviewer_id' => $reviewer->id]);
        $this->outbox->enqueue($permit, $reviewer, 'review_assigned', "review_assigned:{$permit->id}:{$permit->revision_count}");
    }

    public function routeToApprover(Permit $permit): void
    {
        $approver = $this->router->pickApprover($permit->type);
        if (! $approver) {
            $this->flagManual($permit, 'no_approver_available');

            return;
        }
        $permit->update(['approver_id' => $approver->id, 'needs_manual_action' => false, 'manual_action_reason' => null]);
        $this->event($permit, null, 'routed_to_approver', ['approver_id' => $approver->id, 'senior' => $approver->is_senior]);
        $this->outbox->enqueue($permit, $approver, 'approval_requested', "approval_requested:{$permit->id}");
    }

    /** Exception escalation: mark the permit for a person and alert every admin, once per reason per stage. */
    public function flagManual(Permit $permit, string $reason): void
    {
        if ($permit->needs_manual_action && $permit->manual_action_reason === $reason) {
            return; // already flagged for this reason: stay quiet
        }
        $permit->update(['needs_manual_action' => true, 'manual_action_reason' => $reason]);
        $this->event($permit, null, 'manual_action_flagged', ['reason' => $reason]);
        $stage = $permit->stage_entered_at?->getTimestamp() ?? 0;
        foreach (User::where('role', 'admin')->get() as $admin) {
            $this->outbox->enqueue($permit, $admin, 'manual_action_needed', "manual:{$permit->id}:{$reason}:{$stage}:{$admin->id}");
        }
    }

    /** Move between states with a conditional update, so two racing requests cannot both win. */
    private function move(Permit $permit, PermitStatus $to, ?User $actor, string $event, array $meta = []): void
    {
        $from = $permit->status;
        if (! $from->canMoveTo($to)) {
            throw new InvalidTransition($from, $to);
        }

        $changed = Permit::whereKey($permit->id)
            ->where('status', $from->value)
            ->update([
                'status' => $to->value,
                'stage_entered_at' => now(),
                'needs_manual_action' => false, // a new stage clears old exceptions
                'manual_action_reason' => null,
                'updated_at' => now(),
            ]);

        if ($changed !== 1) {
            throw new InvalidTransition($from, $to); // someone else moved it first
        }

        $permit->refresh();
        $this->event($permit, $actor, $event, $meta, $from, $to);
    }

    private function event(Permit $permit, ?User $actor, string $type, array $meta = [], ?PermitStatus $from = null, ?PermitStatus $to = null): void
    {
        PermitEvent::create([
            'permit_id' => $permit->id,
            'actor_id' => $actor?->id,
            'type' => $type,
            'from_status' => $from?->value,
            'to_status' => $to?->value,
            'meta' => $meta ?: null,
            'correlation_id' => StructuredLog::correlationId(),
        ]);

        // Free text (comments, reasons) stays in the database, never in the log.
        StructuredLog::event("permit.{$type}", [
            'permit' => $permit->reference,
            'actor_id' => $actor?->id,
            'from' => $from?->value,
            'to' => $to?->value,
        ] + Arr::except($meta, ['comment', 'reason']));
    }
}
