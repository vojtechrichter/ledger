<?php

declare(strict_types=1);

namespace Shared\Infrastructure\EventStore;

final class SerializationException extends \RuntimeException
{
    public static function unsupportedParameter(string $class, string $parameter): self
    {
        return new self(sprintf("Cannot (de)serialize parameter \$%s of %s: it must have a single scalar, DateTimeImmutable, or class type.", $parameter, $class));
    }

    public static function missingField(string $class, string $field): self
    {
        return new self(sprintf("Payload for %s is missing field '%s'.", $class, $field));
    }

    public static function unexpectedValue(string $class, string $field, string $expected): self
    {
        return new self(sprintf("Payload field '%s' of %s is not a %s.", $field, $class, $expected));
    }
}
