<?php

declare(strict_types=1);

namespace Ledger\Domain\Account\Event;

use Ledger\Domain\Account\AccountId;
use Ledger\Domain\Account\HoldId;
use Shared\Domain\DomainEventInterface;
use Shared\Domain\Money;

final readonly class HoldPlaced implements DomainEventInterface
{
    public function __construct(
        public AccountId $accountId,
        public HoldId $holdId,
        public Money $amount,
        public \DateTimeImmutable $occurredAt,
    ) {
    }
}
