<?php

declare(strict_types=1);

namespace Shared\Infrastructure\EventStore;

use Shared\Domain\DomainEventInterface;

interface EventStoreInterface
{
    /**
     * @param list<DomainEventInterface> $events
     * @return int the stream version after the append
     * @throws ConcurrencyException when the stream is not at $expectedVersion
     */
    #[\NoDiscard]
    public function append(string $streamId, int $expectedVersion, array $events): int;

    /** @return list<DomainEventInterface> empty when the stream does not exist */
    public function load(string $streamId): array;
}
