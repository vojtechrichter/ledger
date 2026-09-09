<?php

declare(strict_types=1);

namespace Ledger\Domain\Exception;

use Ledger\Domain\Account\AccountId;
use Shared\Domain\Money;

final class InsufficientFundsException extends \RuntimeException
{
    public static function for(AccountId $accountId, Money $requested, Money $available): self
    {
        return new self(sprintf("Insufficient funds for account %s. Requested %d, but only %d is available.", $accountId->value, $requested->amount, $available->amount));
    }
}
