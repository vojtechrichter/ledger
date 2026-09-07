<?php

declare(strict_types=1);

namespace Ledger\Domain\Exception;

use Ledger\Domain\Account\AccountId;
use Ledger\Domain\Account\AccountStatus;

final class AccountNotOpenException extends \RuntimeException
{
    public static function for(AccountId $accountId, AccountStatus $status): self
    {
        return new self(sprintf("Account %s is not open. Current status is %s.", $accountId->value, $status->name));
    }
}
