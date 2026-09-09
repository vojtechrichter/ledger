<?php

declare(strict_types=1);

namespace Ledger\Domain\Exception;

use Ledger\Domain\Account\AccountId;
use Shared\Domain\Money;

final class AccountNotEmptyException extends \RuntimeException
{
    public static function for(AccountId $accountId, Money $balance): self
    {
        return new self(sprintf("Account %s cannot be closed with a balance of %d.", $accountId->value, $balance->amount));
    }
}
