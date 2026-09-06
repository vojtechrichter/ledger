<?php

declare(strict_types=1);

namespace Ledger\Domain\Account;

enum AccountStatus
{
    case Open;
    case Frozen;
    case Closed;
}
