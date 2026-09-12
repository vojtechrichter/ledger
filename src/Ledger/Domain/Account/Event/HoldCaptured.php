<?php

declare(strict_types=1);

namespace Ledger\Domain\Account\Event;

use Ledger\Domain\Account\AccountId;
use Ledger\Domain\Account\HoldId;
use Shared\Domain\DomainEventInterface;
use Shared\Domain\EventType;

#[EventType('ledger.hold_captured')]
final readonly class HoldCaptured implements DomainEventInterface
{
    public function __construct(
        public AccountId $accountId,
        public HoldId $holdId,
        public \DateTimeImmutable $occurredAt,
    ) {
    }
}
