<?php

declare(strict_types=1);

namespace Shared\Domain;

interface DomainEventInterface
{
    public \DateTimeImmutable $occurredAt { get; }
}
