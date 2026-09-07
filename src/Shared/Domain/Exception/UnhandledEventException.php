<?php

declare(strict_types=1);

namespace Shared\Domain\Exception;

use Shared\Domain\DomainEventInterface;

final class UnhandledEventException extends \LogicException
{
    public static function for(object $aggregate, DomainEventInterface $event): self
    {
        return new self(sprintf('%s does not handle event %s.', $aggregate::class, $event::class));
    }
}
