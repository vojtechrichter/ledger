<?php

declare(strict_types=1);

namespace Tests\Unit\Ledger\Infrastructure\Persistence;

use Ledger\Infrastructure\Persistence\EventSourcedAccountRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use Shared\Infrastructure\EventStore\EventStoreInterface;
use Tests\Support\AccountRepositoryContractTest;
use Tests\Support\InMemoryEventStore;

#[CoversClass(EventSourcedAccountRepository::class)]
final class EventSourcedAccountRepositoryTest extends AccountRepositoryContractTest
{
    #[\Override]
    protected function createEventStore(): EventStoreInterface
    {
        return new InMemoryEventStore();
    }
}
