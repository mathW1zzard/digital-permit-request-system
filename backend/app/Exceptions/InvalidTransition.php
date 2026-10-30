<?php

namespace App\Exceptions;

use App\Enums\PermitStatus;
use DomainException;

final class InvalidTransition extends DomainException
{
    public function __construct(public readonly PermitStatus $from, public readonly PermitStatus $to)
    {
        parent::__construct("Cannot move permit from {$from->value} to {$to->value}");
    }
}
