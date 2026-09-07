<?php

declare(strict_types=1);

namespace Ledger\Domain\Account;

use Ledger\Domain\Account\Event\AccountOpened;
use Shared\Domain\AbstractAggregateRoot;
use Shared\Domain\DomainEventInterface;
use Shared\Domain\Exception\UnhandledEventException;
use Shared\Domain\Money;

final class Account extends AbstractAggregateRoot
{
    public private(set) AccountId $accountId;
    public private(set) OwnerId $ownerId;
    public private(set) AccountStatus $accountStatus;
    public private(set) Money $balance;
    public private(set) Money $held;

    public static function open(AccountId $accountId, OwnerId $ownerId, string $currency, \DateTimeImmutable $now): self
    {
        $account = new self();
        $account->recordThat(new AccountOpened($accountId, $ownerId, $currency, $now));
        return $account;
    }

    protected function apply(DomainEventInterface $event): void
    {
        match (true) {
            $event instanceof AccountOpened => $this->applyAccountOpened($event),
            default => throw UnhandledEventException::for($this, $event),
        };
    }

    private function applyAccountOpened(AccountOpened $event): void
    {
        $this->accountId = $event->accountId;
        $this->ownerId = $event->ownerId;
        $this->accountStatus = AccountStatus::Open;
        $this->balance = new Money(0, $event->currency);
        $this->held = new Money(0, $event->currency);
    }
}
