<?php

declare(strict_types=1);

namespace Ledger\Domain\Account\Event;

use Ledger\Domain\Account\AccountId;
use Ledger\Domain\Account\HoldId;
use Shared\Domain\DomainEventInterface;
use Shared\Domain\EventType;
use Shared\Domain\Money;

#[EventType('ledger.hold_placed')]
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
