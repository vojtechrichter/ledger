<?php

declare(strict_types=1);

namespace Shared\Domain;

#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class EventType
{
    public function __construct(
        public string $name,
        public int $version = 1,
    ) {
    }
}
