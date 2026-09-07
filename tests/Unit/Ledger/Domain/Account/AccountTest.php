<?php

declare(strict_types=1);

namespace Tests\Unit\Ledger\Domain\Account;

use Ledger\Domain\Account\Account;
use Ledger\Domain\Account\AccountId;
use Ledger\Domain\Account\AccountStatus;
use Ledger\Domain\Account\Event\AccountOpened;
use Ledger\Domain\Account\OwnerId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shared\Domain\AbstractAggregateRoot;
use Shared\Domain\DomainEventInterface;
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

    public function testFreshlyOpenedAccountHasVersionZero(): void
    {
        $account = Account::open($this->accountId, $this->ownerId, 'EUR', $this->now);

        self::assertSame(0, $account->version);
    }
}
