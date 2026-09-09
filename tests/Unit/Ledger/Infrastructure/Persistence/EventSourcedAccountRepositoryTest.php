<?php

declare(strict_types=1);

namespace Tests\Unit\Ledger\Infrastructure\Persistence;

use Ledger\Application\Exception\AccountNotFoundException;
use Ledger\Domain\Account\Account;
use Ledger\Domain\Account\AccountId;
use Ledger\Domain\Account\AccountStatus;
use Ledger\Domain\Account\OwnerId;
use Ledger\Infrastructure\Persistence\EventSourcedAccountRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Money;
use Shared\Infrastructure\EventStore\ConcurrencyException;
use Tests\Support\InMemoryEventStore;

#[CoversClass(EventSourcedAccountRepository::class)]
final class EventSourcedAccountRepositoryTest extends TestCase
{
    private InMemoryEventStore $eventStore;
    private EventSourcedAccountRepository $repository;
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->eventStore = new InMemoryEventStore();
        $this->repository = new EventSourcedAccountRepository($this->eventStore);
        $this->now = new \DateTimeImmutable('2026-09-09 12:00:00');
    }

    public function testSavedAccountIsRebuiltFromItsEvents(): void
    {
        $accountId = AccountId::generate();
        $account = Account::open($accountId, OwnerId::generate(), 'EUR', $this->now);
        $account->deposit(new Money(100, 'EUR'), $this->now);
        $account->withdraw(new Money(30, 'EUR'), $this->now);

        $this->repository->save($account);
        $loaded = $this->repository->get($accountId);

        self::assertNotSame($account, $loaded);
        self::assertTrue($loaded->accountId->equals($accountId));
        self::assertSame(AccountStatus::Open, $loaded->accountStatus);
        self::assertTrue($loaded->balance->equals(new Money(70, 'EUR')));
        self::assertSame(3, $loaded->version);
        self::assertCount(3, $this->eventStore->load($accountId->value));
    }

    public function testUnknownAccountThrows(): void
    {
        $this->expectException(AccountNotFoundException::class);

        $this->repository->get(AccountId::generate());
    }

    public function testSubsequentSavesAppendToTheSameStream(): void
    {
        $accountId = AccountId::generate();
        $this->repository->save(Account::open($accountId, OwnerId::generate(), 'EUR', $this->now));

        $account = $this->repository->get($accountId);
        $account->deposit(new Money(50, 'EUR'), $this->now);
        $this->repository->save($account);

        $account->deposit(new Money(25, 'EUR'), $this->now);
        $this->repository->save($account);

        $loaded = $this->repository->get($accountId);
        self::assertTrue($loaded->balance->equals(new Money(75, 'EUR')));
        self::assertSame(3, $loaded->version);
    }

    public function testSavingWithoutNewEventsAppendsNothing(): void
    {
        $accountId = AccountId::generate();
        $this->repository->save(Account::open($accountId, OwnerId::generate(), 'EUR', $this->now));

        $this->repository->save($this->repository->get($accountId));

        self::assertCount(1, $this->eventStore->load($accountId->value));
    }

    public function testSavingAStaleAccountIsRejected(): void
    {
        $accountId = AccountId::generate();
        $this->repository->save(Account::open($accountId, OwnerId::generate(), 'EUR', $this->now));
        $first = $this->repository->get($accountId);
        $second = $this->repository->get($accountId);

        $first->deposit(new Money(10, 'EUR'), $this->now);
        $this->repository->save($first);

        $second->deposit(new Money(10, 'EUR'), $this->now);

        $this->expectException(ConcurrencyException::class);

        $this->repository->save($second);
    }
}
