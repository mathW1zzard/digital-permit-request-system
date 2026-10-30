<?php

namespace App\Enums;

enum PermitStatus: string
{
    case UnderReview = 'under_review';
    case RevisionRequested = 'revision_requested';
    case PendingApproval = 'pending_approval';
    case Issued = 'issued';
    case Rejected = 'rejected';
    case Expired = 'expired';

    /** The only moves the workflow allows. Anything else is refused with HTTP 409. */
    public function allowedNext(): array
    {
        return match ($this) {
            self::UnderReview => [self::RevisionRequested, self::PendingApproval, self::Rejected],
            self::RevisionRequested => [self::UnderReview],
            self::PendingApproval => [self::Issued, self::Rejected],
            self::Issued => [self::Expired],
            self::Rejected, self::Expired => [],
        };
    }

    public function canMoveTo(self $to): bool
    {
        return in_array($to, $this->allowedNext(), true);
    }
}
