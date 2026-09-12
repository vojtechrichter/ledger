<?php

declare(strict_types=1);

namespace Tests\Integration\Ledger\Infrastructure\Persistence;

use Ledger\Infrastructure\Persistence\EventSourcedAccountRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use Shared\Infrastructure\EventStore\EventStoreInterface;
use Shared\Infrastructure\EventStore\PostgresEventStore;
use Tests\Support\AccountRepositoryContractTest;
use Tests\Support\Database;
use Tests\Support\LedgerEvents;

#[CoversClass(EventSourcedAccountRepository::class)]
#[CoversClass(PostgresEventStore::class)]
final class PostgresAccountRepositoryTest extends AccountRepositoryContractTest
{
    #[\Override]
    protected function createEventStore(): EventStoreInterface
    {
        Database::truncateEventStore();
        return new PostgresEventStore(Database::connection(), LedgerEvents::registry(), new \Shared\Infrastructure\EventStore\EventSerializer());
    }
}
