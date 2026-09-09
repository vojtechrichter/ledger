<?php

declare(strict_types=1);

namespace Ledger\Domain\Account\Event;

use Ledger\Domain\Account\AccountId;
use Shared\Domain\DomainEventInterface;
use Shared\Domain\Money;

final readonly class MoneyWithdrawn implements DomainEventInterface
{
    public function __construct(
        public AccountId $accountId,
        public Money $amount,
        public \DateTimeImmutable $occurredAt,
    ) {
    }
}
