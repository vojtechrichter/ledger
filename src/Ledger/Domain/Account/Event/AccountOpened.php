<?php

declare(strict_types=1);

namespace Ledger\Domain\Account\Event;

use Ledger\Domain\Account\AccountId;
use Ledger\Domain\Account\OwnerId;
use Shared\Domain\DomainEventInterface;
use Shared\Domain\EventType;

#[EventType('ledger.account_opened')]
final readonly class AccountOpened implements DomainEventInterface
{
    public function __construct(
        public AccountId          $accountId,
        public OwnerId            $ownerId,
        public string             $currency,
        public \DateTimeImmutable $occurredAt,
    ) {
    }
}
