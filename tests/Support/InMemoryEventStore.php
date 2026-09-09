<?php

declare(strict_types=1);

namespace Tests\Support;

use Shared\Domain\DomainEventInterface;
use Shared\Infrastructure\EventStore\ConcurrencyException;
use Shared\Infrastructure\EventStore\EventStoreInterface;

final class InMemoryEventStore implements EventStoreInterface
{
    /** @var array<string, list<DomainEventInterface>> */
    private array $streams = [];

    #[\Override]
    public function append(string $streamId, int $expectedVersion, array $events): int
    {
        $stream = $this->streams[$streamId] ?? [];
        $actualVersion = count($stream);
        if ($actualVersion !== $expectedVersion) {
            throw ConcurrencyException::for($streamId, $expectedVersion, $actualVersion);
        }
        $this->streams[$streamId] = [...$stream, ...$events];
        return count($this->streams[$streamId]);
    }

    #[\Override]
    public function load(string $streamId): array
    {
        return $this->streams[$streamId] ?? [];
    }
}
