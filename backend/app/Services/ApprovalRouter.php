<?php

namespace App\Services;

use App\Enums\PermitStatus;
use App\Models\PermitType;
use App\Models\User;

/** Routing rules: least-loaded reviewer; least-loaded approver, senior only when the type demands it. */
final class ApprovalRouter
{
    public function pickReviewer(): ?User
    {
        return User::where('role', 'reviewer')
            ->withCount(['reviewing as open_load' => fn ($q) => $q->where('status', PermitStatus::UnderReview->value)])
            ->orderBy('open_load')
            ->orderBy('id')
            ->first();
    }

    public function pickApprover(PermitType $type): ?User
    {
        return User::where('role', 'approver')
            ->when($type->requires_senior_approval, fn ($q) => $q->where('is_senior', true))
            ->withCount(['approving as open_load' => fn ($q) => $q->where('status', PermitStatus::PendingApproval->value)])
            ->orderBy('open_load')
            ->orderBy('id')
            ->first();
    }
}
