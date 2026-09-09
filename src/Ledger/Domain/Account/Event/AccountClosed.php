<?php

declare(strict_types=1);

namespace Ledger\Domain\Account\Event;

use Ledger\Domain\Account\AccountId;
use Shared\Domain\DomainEventInterface;

final readonly class AccountClosed implements DomainEventInterface
{
    public function __construct(
        public AccountId $accountId,
        public \DateTimeImmutable $occurredAt,
    ) {
    }
}
