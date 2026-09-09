<?php

declare(strict_types=1);

namespace Ledger\Domain\Account;

use Ledger\Domain\Account\Event\AccountOpened;
use Ledger\Domain\Account\Event\HoldCaptured;
use Ledger\Domain\Account\Event\HoldPlaced;
use Ledger\Domain\Account\Event\HoldReleased;
use Ledger\Domain\Account\Event\MoneyDeposited;
use Ledger\Domain\Account\Event\MoneyWithdrawn;
use Ledger\Domain\Exception\AccountNotOpenException;
use Ledger\Domain\Exception\HoldAlreadyPlacedException;
use Ledger\Domain\Exception\HoldNotFoundException;
use Ledger\Domain\Exception\InsufficientFundsException;
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

    /** @var array<string, Money> active holds keyed by hold id */
    private array $holds = [];

    public Money $held {
        get => array_reduce(
            $this->holds,
            static fn (Money $sum, Money $hold): Money => $sum->add($hold),
            new Money(0, $this->balance->currency),
        );
    }

    public static function open(AccountId $accountId, OwnerId $ownerId, string $currency, \DateTimeImmutable $now): self
    {
        $account = new self();
        $account->recordThat(new AccountOpened($accountId, $ownerId, $currency, $now));
        return $account;
    }

    public function deposit(Money $amount, \DateTimeImmutable $now): void
    {
        $this->assertOpen();
        $this->recordThat(new MoneyDeposited($this->accountId, $amount, $now));
    }

    public function withdraw(Money $amount, \DateTimeImmutable $now): void
    {
        $this->assertOpen();
        $this->assertAvailable($amount);
        $this->recordThat(new MoneyWithdrawn($this->accountId, $amount, $now));
    }

    public function placeHold(HoldId $holdId, Money $amount, \DateTimeImmutable $now): void
    {
        $this->assertOpen();
        if (isset($this->holds[$holdId->value])) {
            throw HoldAlreadyPlacedException::for($this->accountId, $holdId);
        }
        $this->assertAvailable($amount);
        $this->recordThat(new HoldPlaced($this->accountId, $holdId, $amount, $now));
    }

    public function releaseHold(HoldId $holdId, \DateTimeImmutable $now): void
    {
        $this->assertHoldExists($holdId);
        $this->recordThat(new HoldReleased($this->accountId, $holdId, $now));
    }

    public function captureHold(HoldId $holdId, \DateTimeImmutable $now): void
    {
        $this->assertHoldExists($holdId);
        $this->recordThat(new HoldCaptured($this->accountId, $holdId, $now));
    }

    public function available(): Money
    {
        return $this->balance->subtract($this->held);
    }

    protected function apply(DomainEventInterface $event): void
    {
        match (true) {
            $event instanceof AccountOpened => $this->applyAccountOpened($event),
            $event instanceof MoneyDeposited => $this->balance = $this->balance->add($event->amount),
            $event instanceof MoneyWithdrawn => $this->balance = $this->balance->subtract($event->amount),
            $event instanceof HoldPlaced => $this->holds[$event->holdId->value] = $event->amount,
            $event instanceof HoldReleased => $this->forgetHold($event->holdId),
            $event instanceof HoldCaptured => $this->applyHoldCaptured($event),
            default => throw UnhandledEventException::for($this, $event),
        };
    }

    private function applyAccountOpened(AccountOpened $event): void
    {
        $this->accountId = $event->accountId;
        $this->ownerId = $event->ownerId;
        $this->accountStatus = AccountStatus::Open;
        $this->balance = new Money(0, $event->currency);
    }

    private function applyHoldCaptured(HoldCaptured $event): void
    {
        $this->balance = $this->balance->subtract($this->holds[$event->holdId->value]);
        $this->forgetHold($event->holdId);
    }

    private function forgetHold(HoldId $holdId): void
    {
        unset($this->holds[$holdId->value]);
    }

    private function assertOpen(): void
    {
        if ($this->accountStatus !== AccountStatus::Open) {
            throw AccountNotOpenException::for($this->accountId, $this->accountStatus);
        }
    }

    private function assertAvailable(Money $amount): void
    {
        if ($this->available()->subtract($amount)->isNegative()) {
            throw InsufficientFundsException::for($this->accountId, $amount, $this->available());
        }
    }

    private function assertHoldExists(HoldId $holdId): void
    {
        if (!isset($this->holds[$holdId->value])) {
            throw HoldNotFoundException::for($this->accountId, $holdId);
        }
    }
}
