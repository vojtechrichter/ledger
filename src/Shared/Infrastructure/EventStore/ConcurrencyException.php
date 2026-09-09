<?php

declare(strict_types=1);

namespace Shared\Infrastructure\EventStore;

final class ConcurrencyException extends \RuntimeException
{
    public static function for(string $streamId, int $expectedVersion, int $actualVersion): self
    {
        return new self(sprintf("Stream %s is at version %d, expected %d.", $streamId, $actualVersion, $expectedVersion));
    }
}
