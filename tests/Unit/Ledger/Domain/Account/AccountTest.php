<?php

declare(strict_types=1);

namespace Tests\Unit\Ledger\Domain\Account;

use Ledger\Domain\Account\Account;
use Ledger\Domain\Account\AccountId;
use Ledger\Domain\Account\AccountStatus;
use Ledger\Domain\Account\Event\AccountClosed;
use Ledger\Domain\Account\Event\AccountFrozen;
use Ledger\Domain\Account\Event\AccountOpened;
use Ledger\Domain\Account\Event\AccountUnfrozen;
use Ledger\Domain\Account\Event\HoldCaptured;
use Ledger\Domain\Account\Event\HoldPlaced;
use Ledger\Domain\Account\Event\HoldReleased;
use Ledger\Domain\Account\Event\MoneyDeposited;
use Ledger\Domain\Account\Event\MoneyWithdrawn;
use Ledger\Domain\Account\HoldId;
use Ledger\Domain\Account\OwnerId;
use Ledger\Domain\Exception\AccountAlreadyClosedException;
use Ledger\Domain\Exception\AccountNotEmptyException;
use Ledger\Domain\Exception\AccountNotFrozenException;
use Ledger\Domain\Exception\AccountNotOpenException;
use Ledger\Domain\Exception\HoldAlreadyPlacedException;
use Ledger\Domain\Exception\HoldNotFoundException;
use Ledger\Domain\Exception\InsufficientFundsException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shared\Domain\AbstractAggregateRoot;
use Shared\Domain\DomainEventInterface;
use Shared\Domain\Exception\CurrencyMismatchException;
use Shared\Domain\Exception\UnhandledEventException;
use Shared\Domain\Money;

#[CoversClass(Account::class)]
#[CoversClass(AbstractAggregateRoot::class)]
final class AccountTest extends TestCase
{
    private AccountId $accountId;
    private OwnerId $ownerId;
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->accountId = AccountId::generate();
        $this->ownerId = OwnerId::generate();
        $this->now = new \DateTimeImmutable('2026-09-07 12:00:00');
    }

    public function testOpenRecordsAccountOpened(): void
    {
        $account = Account::open($this->accountId, $this->ownerId, 'EUR', $this->now);

        $events = $account->releaseEvents();

        self::assertCount(1, $events);
        $event = $events[0];
        self::assertInstanceOf(AccountOpened::class, $event);
        self::assertTrue($event->accountId->equals($this->accountId));
        self::assertTrue($event->ownerId->equals($this->ownerId));
        self::assertSame('EUR', $event->currency);
        self::assertSame($this->now, $event->occurredAt);
    }

    public function testOpenedAccountStartsEmptyAndOpen(): void
    {
        $account = Account::open($this->accountId, $this->ownerId, 'EUR', $this->now);

        self::assertTrue($account->accountId->equals($this->accountId));
        self::assertTrue($account->ownerId->equals($this->ownerId));
        self::assertSame(AccountStatus::Open, $account->accountStatus);
        self::assertTrue($account->balance->equals(new Money(0, 'EUR')));
        self::assertTrue($account->held->equals(new Money(0, 'EUR')));
    }

    public function testReleaseEventsClearsRecordedEvents(): void
    {
        $account = Account::open($this->accountId, $this->ownerId, 'EUR', $this->now);

        $account->releaseEvents();

        self::assertSame([], $account->releaseEvents());
    }

    public function testReconstituteReplaysHistoryWithoutRecording(): void
    {
        $account = Account::reconstitute([
            new AccountOpened($this->accountId, $this->ownerId, 'EUR', $this->now),
        ]);

        self::assertSame(1, $account->version);
        self::assertSame([], $account->releaseEvents());
        self::assertSame(AccountStatus::Open, $account->accountStatus);
        self::assertTrue($account->balance->equals(new Money(0, 'EUR')));
    }

    public function testUnknownEventInHistoryThrows(): void
    {
        $this->expectException(UnhandledEventException::class);

        Account::reconstitute([new class implements DomainEventInterface {}]);
    }

    public function testVersionCountsOnlyReleasedEvents(): void
    {
        $account = Account::open($this->accountId, $this->ownerId, 'EUR', $this->now);
        self::assertSame(0, $account->version);

        $account->releaseEvents();
        self::assertSame(1, $account->version);

        $account->deposit(new Money(10, 'EUR'), $this->now);
        $account->deposit(new Money(10, 'EUR'), $this->now);
        self::assertSame(1, $account->version);

        $account->releaseEvents();
        self::assertSame(3, $account->version);
    }

    public function testDepositRecordsMoneyDepositedAndRaisesBalance(): void
    {
        $account = $this->openAccount();

        $account->deposit(new Money(100, 'EUR'), $this->now);

        $events = $account->releaseEvents();
        self::assertCount(1, $events);
        $event = $events[0];
        self::assertInstanceOf(MoneyDeposited::class, $event);
        self::assertTrue($event->accountId->equals($this->accountId));
        self::assertTrue($event->amount->equals(new Money(100, 'EUR')));
        self::assertSame($this->now, $event->occurredAt);
        self::assertTrue($account->balance->equals(new Money(100, 'EUR')));
    }

    public function testDepositsAccumulate(): void
    {
        $account = $this->openAccount();

        $account->deposit(new Money(100, 'EUR'), $this->now);
        $account->deposit(new Money(50, 'EUR'), $this->now);

        self::assertCount(2, $account->releaseEvents());
        self::assertTrue($account->balance->equals(new Money(150, 'EUR')));
    }

    public function testDepositInAnotherCurrencyIsRejectedAndRecordsNothing(): void
    {
        $account = $this->openAccount();

        try {
            $account->deposit(new Money(100, 'USD'), $this->now);
            self::fail('Expected ' . CurrencyMismatchException::class);
        } catch (CurrencyMismatchException) {
        }

        self::assertSame([], $account->releaseEvents());
        self::assertTrue($account->balance->equals(new Money(0, 'EUR')));
    }

    public function testDepositIsReplayedFromHistory(): void
    {
        $account = Account::reconstitute([
            new AccountOpened($this->accountId, $this->ownerId, 'EUR', $this->now),
            new MoneyDeposited($this->accountId, new Money(100, 'EUR'), $this->now),
        ]);

        self::assertSame(2, $account->version);
        self::assertTrue($account->balance->equals(new Money(100, 'EUR')));
    }

    public function testWithdrawRecordsMoneyWithdrawnAndLowersBalance(): void
    {
        $account = $this->accountWithBalance(100);

        $account->withdraw(new Money(30, 'EUR'), $this->now);

        $events = $account->releaseEvents();
        self::assertCount(1, $events);
        $event = $events[0];
        self::assertInstanceOf(MoneyWithdrawn::class, $event);
        self::assertTrue($event->accountId->equals($this->accountId));
        self::assertTrue($event->amount->equals(new Money(30, 'EUR')));
        self::assertSame($this->now, $event->occurredAt);
        self::assertTrue($account->balance->equals(new Money(70, 'EUR')));
    }

    public function testWithdrawingExactlyTheAvailableAmountLeavesZero(): void
    {
        $account = $this->accountWithBalance(100);

        $account->withdraw(new Money(100, 'EUR'), $this->now);

        self::assertTrue($account->balance->equals(new Money(0, 'EUR')));
    }

    public function testWithdrawingMoreThanAvailableIsRejectedAndRecordsNothing(): void
    {
        $account = $this->accountWithBalance(100);

        $this->assertRejected(
            InsufficientFundsException::class,
            fn () => $account->withdraw(new Money(101, 'EUR'), $this->now),
        );

        self::assertSame([], $account->releaseEvents());
        self::assertTrue($account->balance->equals(new Money(100, 'EUR')));
    }

    public function testWithdrawingFromEmptyAccountIsRejected(): void
    {
        $account = $this->openAccount();

        $this->expectException(InsufficientFundsException::class);

        $account->withdraw(new Money(1, 'EUR'), $this->now);
    }

    public function testWithdrawIsReplayedFromHistory(): void
    {
        $account = Account::reconstitute([
            new AccountOpened($this->accountId, $this->ownerId, 'EUR', $this->now),
            new MoneyDeposited($this->accountId, new Money(100, 'EUR'), $this->now),
            new MoneyWithdrawn($this->accountId, new Money(30, 'EUR'), $this->now),
        ]);

        self::assertSame(3, $account->version);
        self::assertTrue($account->balance->equals(new Money(70, 'EUR')));
    }

    public function testPlaceHoldReservesMoneyWithoutTouchingBalance(): void
    {
        $account = $this->accountWithBalance(100);
        $holdId = HoldId::generate();

        $account->placeHold($holdId, new Money(30, 'EUR'), $this->now);

        $events = $account->releaseEvents();
        self::assertCount(1, $events);
        $event = $events[0];
        self::assertInstanceOf(HoldPlaced::class, $event);
        self::assertTrue($event->accountId->equals($this->accountId));
        self::assertTrue($event->holdId->equals($holdId));
        self::assertTrue($event->amount->equals(new Money(30, 'EUR')));
        self::assertSame($this->now, $event->occurredAt);
        self::assertTrue($account->balance->equals(new Money(100, 'EUR')));
        self::assertTrue($account->held->equals(new Money(30, 'EUR')));
        self::assertTrue($account->available()->equals(new Money(70, 'EUR')));
    }

    public function testMultipleHoldsAccumulate(): void
    {
        $account = $this->accountWithBalance(100);

        $account->placeHold(HoldId::generate(), new Money(30, 'EUR'), $this->now);
        $account->placeHold(HoldId::generate(), new Money(20, 'EUR'), $this->now);

        self::assertTrue($account->held->equals(new Money(50, 'EUR')));
        self::assertTrue($account->available()->equals(new Money(50, 'EUR')));
    }

    public function testPlacingHoldBeyondAvailableIsRejectedAndRecordsNothing(): void
    {
        $account = $this->accountWithBalance(100);
        $account->placeHold(HoldId::generate(), new Money(60, 'EUR'), $this->now);
        $account->releaseEvents();

        $this->assertRejected(
            InsufficientFundsException::class,
            fn () => $account->placeHold(HoldId::generate(), new Money(41, 'EUR'), $this->now),
        );

        self::assertSame([], $account->releaseEvents());
        self::assertTrue($account->held->equals(new Money(60, 'EUR')));
    }

    public function testPlacingTheSameHoldTwiceIsRejected(): void
    {
        $account = $this->accountWithBalance(100);
        $holdId = HoldId::generate();
        $account->placeHold($holdId, new Money(10, 'EUR'), $this->now);

        $this->expectException(HoldAlreadyPlacedException::class);

        $account->placeHold($holdId, new Money(10, 'EUR'), $this->now);
    }

    public function testWithdrawRespectsHeldAmount(): void
    {
        $account = $this->accountWithBalance(100);
        $account->placeHold(HoldId::generate(), new Money(30, 'EUR'), $this->now);

        $this->assertRejected(
            InsufficientFundsException::class,
            fn () => $account->withdraw(new Money(71, 'EUR'), $this->now),
        );

        $account->withdraw(new Money(70, 'EUR'), $this->now);

        self::assertTrue($account->balance->equals(new Money(30, 'EUR')));
        self::assertTrue($account->available()->equals(new Money(0, 'EUR')));
    }

    public function testReleaseHoldMakesMoneyAvailableAgain(): void
    {
        $account = $this->accountWithBalance(100);
        $holdId = HoldId::generate();
        $account->placeHold($holdId, new Money(30, 'EUR'), $this->now);
        $account->releaseEvents();

        $account->releaseHold($holdId, $this->now);

        $events = $account->releaseEvents();
        self::assertCount(1, $events);
        $event = $events[0];
        self::assertInstanceOf(HoldReleased::class, $event);
        self::assertTrue($event->holdId->equals($holdId));
        self::assertTrue($account->balance->equals(new Money(100, 'EUR')));
        self::assertTrue($account->held->equals(new Money(0, 'EUR')));
    }

    public function testReleasingUnknownHoldIsRejected(): void
    {
        $account = $this->accountWithBalance(100);

        $this->expectException(HoldNotFoundException::class);

        $account->releaseHold(HoldId::generate(), $this->now);
    }

    public function testCaptureHoldDebitsBalanceAndRemovesHold(): void
    {
        $account = $this->accountWithBalance(100);
        $holdId = HoldId::generate();
        $account->placeHold($holdId, new Money(30, 'EUR'), $this->now);
        $account->releaseEvents();

        $account->captureHold($holdId, $this->now);

        $events = $account->releaseEvents();
        self::assertCount(1, $events);
        $event = $events[0];
        self::assertInstanceOf(HoldCaptured::class, $event);
        self::assertTrue($event->holdId->equals($holdId));
        self::assertTrue($account->balance->equals(new Money(70, 'EUR')));
        self::assertTrue($account->held->equals(new Money(0, 'EUR')));
        self::assertTrue($account->available()->equals(new Money(70, 'EUR')));
    }

    public function testCapturingUnknownHoldIsRejected(): void
    {
        $account = $this->accountWithBalance(100);

        $this->expectException(HoldNotFoundException::class);

        $account->captureHold(HoldId::generate(), $this->now);
    }

    public function testHoldLifecycleIsReplayedFromHistory(): void
    {
        $captured = HoldId::generate();
        $released = HoldId::generate();
        $account = Account::reconstitute([
            new AccountOpened($this->accountId, $this->ownerId, 'EUR', $this->now),
            new MoneyDeposited($this->accountId, new Money(100, 'EUR'), $this->now),
            new HoldPlaced($this->accountId, $captured, new Money(30, 'EUR'), $this->now),
            new HoldPlaced($this->accountId, $released, new Money(20, 'EUR'), $this->now),
            new HoldReleased($this->accountId, $released, $this->now),
            new HoldCaptured($this->accountId, $captured, $this->now),
        ]);

        self::assertSame(6, $account->version);
        self::assertTrue($account->balance->equals(new Money(70, 'EUR')));
        self::assertTrue($account->held->equals(new Money(0, 'EUR')));
    }

    public function testFreezeRecordsAccountFrozen(): void
    {
        $account = $this->openAccount();

        $account->freeze($this->now);

        $events = $account->releaseEvents();
        self::assertCount(1, $events);
        $event = $events[0];
        self::assertInstanceOf(AccountFrozen::class, $event);
        self::assertTrue($event->accountId->equals($this->accountId));
        self::assertSame($this->now, $event->occurredAt);
        self::assertSame(AccountStatus::Frozen, $account->accountStatus);
    }

    public function testFrozenAccountRejectsMoneyMovements(): void
    {
        $account = $this->accountWithBalance(100);
        $account->freeze($this->now);
        $account->releaseEvents();

        $this->assertRejected(AccountNotOpenException::class, fn () => $account->deposit(new Money(10, 'EUR'), $this->now));
        $this->assertRejected(AccountNotOpenException::class, fn () => $account->withdraw(new Money(10, 'EUR'), $this->now));
        $this->assertRejected(AccountNotOpenException::class, fn () => $account->placeHold(HoldId::generate(), new Money(10, 'EUR'), $this->now));

        self::assertSame([], $account->releaseEvents());
    }

    public function testFrozenAccountStillAllowsHoldsToBeReleasedAndCaptured(): void
    {
        $account = $this->accountWithBalance(100);
        $released = HoldId::generate();
        $captured = HoldId::generate();
        $account->placeHold($released, new Money(30, 'EUR'), $this->now);
        $account->placeHold($captured, new Money(20, 'EUR'), $this->now);
        $account->freeze($this->now);

        $account->releaseHold($released, $this->now);
        $account->captureHold($captured, $this->now);

        self::assertTrue($account->balance->equals(new Money(80, 'EUR')));
        self::assertTrue($account->held->equals(new Money(0, 'EUR')));
    }

    public function testFreezingANonOpenAccountIsRejected(): void
    {
        $account = $this->openAccount();
        $account->freeze($this->now);

        $this->expectException(AccountNotOpenException::class);

        $account->freeze($this->now);
    }

    public function testUnfreezeReopensTheAccount(): void
    {
        $account = $this->openAccount();
        $account->freeze($this->now);
        $account->releaseEvents();

        $account->unfreeze($this->now);

        $events = $account->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(AccountUnfrozen::class, $events[0]);
        self::assertSame(AccountStatus::Open, $account->accountStatus);
        $account->deposit(new Money(10, 'EUR'), $this->now);
        self::assertTrue($account->balance->equals(new Money(10, 'EUR')));
    }

    public function testUnfreezingAnOpenAccountIsRejected(): void
    {
        $account = $this->openAccount();

        $this->expectException(AccountNotFrozenException::class);

        $account->unfreeze($this->now);
    }

    public function testCloseRecordsAccountClosed(): void
    {
        $account = $this->openAccount();

        $account->close($this->now);

        $events = $account->releaseEvents();
        self::assertCount(1, $events);
        $event = $events[0];
        self::assertInstanceOf(AccountClosed::class, $event);
        self::assertTrue($event->accountId->equals($this->accountId));
        self::assertSame($this->now, $event->occurredAt);
        self::assertSame(AccountStatus::Closed, $account->accountStatus);
    }

    public function testClosingAnAccountWithBalanceIsRejected(): void
    {
        $account = $this->accountWithBalance(1);

        $this->expectException(AccountNotEmptyException::class);

        $account->close($this->now);
    }

    public function testClosingTwiceIsRejected(): void
    {
        $account = $this->openAccount();
        $account->close($this->now);

        $this->expectException(AccountAlreadyClosedException::class);

        $account->close($this->now);
    }

    public function testClosedAccountRejectsMoneyMovementsAndFreezing(): void
    {
        $account = $this->openAccount();
        $account->close($this->now);
        $account->releaseEvents();

        $this->assertRejected(AccountNotOpenException::class, fn () => $account->deposit(new Money(10, 'EUR'), $this->now));
        $this->assertRejected(AccountNotOpenException::class, fn () => $account->freeze($this->now));
        $this->assertRejected(AccountNotFrozenException::class, fn () => $account->unfreeze($this->now));

        self::assertSame([], $account->releaseEvents());
    }

    public function testStatusChangesAreReplayedFromHistory(): void
    {
        $account = Account::reconstitute([
            new AccountOpened($this->accountId, $this->ownerId, 'EUR', $this->now),
            new AccountFrozen($this->accountId, $this->now),
            new AccountUnfrozen($this->accountId, $this->now),
            new AccountClosed($this->accountId, $this->now),
        ]);

        self::assertSame(4, $account->version);
        self::assertSame(AccountStatus::Closed, $account->accountStatus);
    }

    private function accountWithBalance(int $amount): Account
    {
        $account = $this->openAccount();
        $account->deposit(new Money($amount, 'EUR'), $this->now);
        $account->releaseEvents();
        return $account;
    }

    /**
     * @param class-string<\Throwable> $exception
     * @param callable(): void $command
     */
    private function assertRejected(string $exception, callable $command): void
    {
        try {
            $command();
        } catch (\Throwable $e) {
            self::assertInstanceOf($exception, $e);
            return;
        }
        self::fail('Expected ' . $exception);
    }

    private function openAccount(): Account
    {
        $account = Account::open($this->accountId, $this->ownerId, 'EUR', $this->now);
        $account->releaseEvents();
        return $account;
    }
}
