<?php

declare(strict_types=1);

namespace Ledger\Domain\Exception;

use Ledger\Domain\Account\AccountId;
use Ledger\Domain\Account\HoldId;

final class HoldNotFoundException extends \RuntimeException
{
    public static function for(AccountId $accountId, HoldId $holdId): self
    {
        return new self(sprintf("Account %s has no hold %s.", $accountId->value, $holdId->value));
    }
}
