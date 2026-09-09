<?php

declare(strict_types=1);

namespace Ledger\Domain\Exception;

use Ledger\Domain\Account\AccountId;
use Ledger\Domain\Account\HoldId;

final class HoldAlreadyPlacedException extends \RuntimeException
{
    public static function for(AccountId $accountId, HoldId $holdId): self
    {
        return new self(sprintf("Account %s already has hold %s.", $accountId->value, $holdId->value));
    }
}
