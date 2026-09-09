<?php

declare(strict_types=1);

namespace Ledger\Application\Exception;

use Ledger\Domain\Account\AccountId;

final class AccountNotFoundException extends \RuntimeException
{
    public static function withId(AccountId $accountId): self
    {
        return new self(sprintf("Account %s does not exist.", $accountId->value));
    }
}
