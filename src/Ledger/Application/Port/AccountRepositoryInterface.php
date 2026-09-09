<?php

declare(strict_types=1);

namespace Ledger\Application\Port;

use Ledger\Application\Exception\AccountNotFoundException;
use Ledger\Domain\Account\Account;
use Ledger\Domain\Account\AccountId;

interface AccountRepositoryInterface
{
    /** @throws AccountNotFoundException */
    public function get(AccountId $accountId): Account;

    public function save(Account $account): void;
}
