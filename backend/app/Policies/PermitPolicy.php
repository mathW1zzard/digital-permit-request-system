<?php

namespace App\Policies;

use App\Models\Permit;
use App\Models\User;

final class PermitPolicy
{
    public function view(User $user, Permit $permit): bool
    {
        return in_array($user->id, [$permit->requester_id, $permit->reviewer_id, $permit->approver_id], true)
            || in_array($user->role, ['admin', 'inspector'], true);
    }

    public function review(User $user, Permit $permit): bool
    {
        return $user->role === 'reviewer' && $user->id === $permit->reviewer_id;
    }

    public function approve(User $user, Permit $permit): bool
    {
        return $user->role === 'approver' && $user->id === $permit->approver_id;
    }

    public function reject(User $user, Permit $permit): bool
    {
        return $this->review($user, $permit) || $this->approve($user, $permit);
    }

    public function resubmit(User $user, Permit $permit): bool
    {
        return $user->id === $permit->requester_id;
    }
}
