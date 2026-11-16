<?php

namespace App\Exceptions;

use App\Enums\PermitStatus;
use DomainException;

final class InvalidTransition extends DomainException
{
    /**
     * @param  PermitStatus|null  $current  The status in the database when the change was refused.
     * @param  bool  $race  True when someone else changed the permit between our read and our write.
     */
    public function __construct(
        public readonly PermitStatus $from,
        public readonly PermitStatus $to,
        public readonly ?PermitStatus $current = null,
        public readonly bool $race = false,
    ) {
        parent::__construct("Cannot move permit from {$from->value} to {$to->value}");
    }

    /**
     * Week 17 (UAT finding F-04): the losing side of a race used to read "not available while the
     * permit is under review" although the permit had just become pending approval. Say what happened.
     */
    public function userMessage(): string
    {
        $now = str_replace('_', ' ', ($this->current ?? $this->from)->value);

        return $this->race
            ? "Someone else acted on this permit first. It is now {$now}."
            : "This action is not available while the permit is {$now}.";
    }
}
