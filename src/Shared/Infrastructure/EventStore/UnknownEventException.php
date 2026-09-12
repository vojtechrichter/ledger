<?php

declare(strict_types=1);

namespace Shared\Infrastructure\EventStore;

final class UnknownEventException extends \RuntimeException
{
    public static function forName(string $name): self
    {
        return new self(sprintf("No event class is registered for type '%s'.", $name));
    }

    public static function forClass(string $class): self
    {
        return new self(sprintf("Event class %s is not registered.", $class));
    }

    public static function missingAttribute(string $class): self
    {
        return new self(sprintf("Event class %s has no #[EventType] attribute.", $class));
    }
}
