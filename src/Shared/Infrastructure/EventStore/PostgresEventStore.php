<?php

declare(strict_types=1);

namespace Shared\Infrastructure\EventStore;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Types\Types;
use Shared\Domain\DomainEventInterface;

final readonly class PostgresEventStore implements EventStoreInterface
{
    public function __construct(
        private Connection $connection,
        private EventRegistry $registry,
        private EventSerializer $serializer,
    ) {
    }

    #[\NoDiscard]
    #[\Override]
    public function append(string $streamId, int $expectedVersion, array $events): int
    {
        try {
            $this->connection->transactional(function () use ($streamId, $expectedVersion, $events): void {
                foreach ($events as $offset => $event) {
                    $this->connection->insert('events', [
                        'stream_id' => $streamId,
                        'stream_version' => $expectedVersion + $offset + 1,
                        'event_type' => $this->registry->nameOf($event),
                        'event_version' => $this->registry->versionOf($event),
                        'payload' => $this->serializer->serialize($event),
                        'metadata' => new \stdClass(),
                        'occurred_at' => $event->occurredAt->format('Y-m-d H:i:s.uP'),
                    ], [
                        'payload' => Types::JSON,
                        'metadata' => Types::JSON,
                    ]);
                }
            });
        } catch (UniqueConstraintViolationException) {
            throw ConcurrencyException::for($streamId, $expectedVersion, $this->currentVersion($streamId));
        }

        return $expectedVersion + count($events);
    }

    #[\Override]
    public function load(string $streamId): array
    {
        /** @var list<array{event_type: string, payload: string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT event_type, payload FROM events WHERE stream_id = ? ORDER BY stream_version',
            [$streamId],
        );

        return array_map(function (array $row): DomainEventInterface {
            /** @var array<string, mixed> $payload */
            $payload = json_decode($row['payload'], true, flags: JSON_THROW_ON_ERROR);
            return $this->serializer->deserialize($this->registry->classFor($row['event_type']), $payload);
        }, $rows);
    }

    private function currentVersion(string $streamId): int
    {
        $version = $this->connection->fetchOne(
            'SELECT COALESCE(MAX(stream_version), 0) FROM events WHERE stream_id = ?',
            [$streamId],
        );
        return is_int($version) ? $version : (int) (is_string($version) ? $version : 0);
    }
}
