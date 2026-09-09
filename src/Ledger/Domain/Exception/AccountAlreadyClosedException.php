<?php

declare(strict_types=1);

namespace Ledger\Domain\Exception;

use Ledger\Domain\Account\AccountId;

final class AccountAlreadyClosedException extends \RuntimeException
{
    public static function for(AccountId $accountId): self
    {
        return new self(sprintf("Account %s is already closed.", $accountId->value));
    }
}
