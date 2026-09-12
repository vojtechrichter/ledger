<?php

declare(strict_types=1);

namespace Tests\Integration\Shared\Infrastructure\EventStore;

use Ledger\Domain\Account\AccountId;
use Ledger\Domain\Account\Event\AccountOpened;
use Ledger\Domain\Account\Event\MoneyDeposited;
use Ledger\Domain\Account\OwnerId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Money;
use Shared\Infrastructure\EventStore\ConcurrencyException;
use Shared\Infrastructure\EventStore\EventSerializer;
use Shared\Infrastructure\EventStore\PostgresEventStore;
use Tests\Support\Database;
use Tests\Support\LedgerEvents;

#[CoversClass(PostgresEventStore::class)]
final class PostgresEventStoreTest extends TestCase
{
    private PostgresEventStore $store;
    private AccountId $accountId;
    private \DateTimeImmutable $at;

    protected function setUp(): void
    {
        Database::truncateEventStore();
        $this->store = new PostgresEventStore(Database::connection(), LedgerEvents::registry(), new EventSerializer());
        $this->accountId = AccountId::generate();
        $this->at = new \DateTimeImmutable('2026-09-12 08:30:15.123456+02:00');
    }

    public function testAppendedEventsAreLoadedInOrder(): void
    {
        $opened = new AccountOpened($this->accountId, OwnerId::generate(), 'EUR', $this->at);
        $deposited = new MoneyDeposited($this->accountId, new Money(100, 'EUR'), $this->at);

        $version = $this->store->append($this->accountId->value, 0, [$opened, $deposited]);

        self::assertSame(2, $version);
        self::assertEquals([$opened, $deposited], $this->store->load($this->accountId->value));
    }

    public function testUnknownStreamLoadsEmpty(): void
    {
        self::assertSame([], $this->store->load(AccountId::generate()->value));
    }

    public function testRowsCarryTypeVersionPayloadAndTimestamp(): void
    {
        (void) $this->store->append($this->accountId->value, 0, [
            new MoneyDeposited($this->accountId, new Money(100, 'EUR'), $this->at),
        ]);

        $row = Database::connection()->fetchAssociative(
            'SELECT stream_id::text, stream_version, event_type, event_version, payload::text, metadata::text, occurred_at::text FROM events',
        );

        self::assertNotFalse($row);
        self::assertSame($this->accountId->value, $row['stream_id']);
        self::assertSame(1, $row['stream_version']);
        self::assertSame('ledger.money_deposited', $row['event_type']);
        self::assertSame(1, $row['event_version']);
        self::assertIsString($row['payload']);
        /** @var array<string, mixed> $payload */
        $payload = json_decode($row['payload'], true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(['amount' => 100, 'currency' => 'EUR'], $payload['amount']);
        self::assertSame('{}', $row['metadata']);
        self::assertIsString($row['occurred_at']);
        self::assertStringStartsWith('2026-09-12 06:30:15.123456', $row['occurred_at']);
    }

    public function testStaleExpectedVersionIsRejectedAtomically(): void
    {
        $opened = new AccountOpened($this->accountId, OwnerId::generate(), 'EUR', $this->at);
        (void) $this->store->append($this->accountId->value, 0, [$opened]);

        try {
            (void) $this->store->append($this->accountId->value, 0, [
                new MoneyDeposited($this->accountId, new Money(1, 'EUR'), $this->at),
                new MoneyDeposited($this->accountId, new Money(2, 'EUR'), $this->at),
            ]);
            self::fail('Expected ' . ConcurrencyException::class);
        } catch (ConcurrencyException $e) {
            self::assertStringContainsString('is at version 1, expected 0', $e->getMessage());
        }

        self::assertCount(1, $this->store->load($this->accountId->value));
    }

    public function testStreamsAreIsolated(): void
    {
        $other = AccountId::generate();
        (void) $this->store->append($this->accountId->value, 0, [new AccountOpened($this->accountId, OwnerId::generate(), 'EUR', $this->at)]);
        (void) $this->store->append($other->value, 0, [new AccountOpened($other, OwnerId::generate(), 'EUR', $this->at)]);

        self::assertCount(1, $this->store->load($this->accountId->value));
        self::assertCount(1, $this->store->load($other->value));
    }
}
