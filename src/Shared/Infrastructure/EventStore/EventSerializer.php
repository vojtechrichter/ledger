<?php

declare(strict_types=1);

namespace Shared\Infrastructure\EventStore;

use Shared\Domain\DomainEventInterface;

final class EventSerializer
{
    private const string DATE_FORMAT = 'Y-m-d\TH:i:s.uP';

    /** @return array<string, mixed> */
    public function serialize(DomainEventInterface $event): array
    {
        return $this->toArray($event);
    }

    /**
     * @param class-string<DomainEventInterface> $class
     * @param array<string, mixed> $payload
     */
    public function deserialize(string $class, array $payload): DomainEventInterface
    {
        return $this->fromArray($class, $payload);
    }

    /** @return array<string, mixed> */
    private function toArray(object $object): array
    {
        $data = [];
        foreach ($this->parametersOf($object::class) as $parameter) {
            $name = $parameter->getName();
            $data[$name] = $this->normalize($object->{$name}, $object::class, $name);
        }
        return $data;
    }

    private function normalize(mixed $value, string $class, string $field): mixed
    {
        return match (true) {
            $value instanceof \DateTimeImmutable => $value->format(self::DATE_FORMAT),
            is_object($value) => $this->toArray($value),
            is_scalar($value), $value === null => $value,
            default => throw SerializationException::unsupportedParameter($class, $field),
        };
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @param array<string, mixed> $data
     * @return T
     */
    private function fromArray(string $class, array $data): object
    {
        $arguments = [];
        foreach ($this->parametersOf($class) as $parameter) {
            $name = $parameter->getName();
            if (!array_key_exists($name, $data)) {
                throw SerializationException::missingField($class, $name);
            }
            $arguments[$name] = $this->denormalize($parameter, $data[$name], $class);
        }
        return new $class(...$arguments);
    }

    private function denormalize(\ReflectionParameter $parameter, mixed $value, string $class): mixed
    {
        $type = $parameter->getType();
        $name = $parameter->getName();
        if (!$type instanceof \ReflectionNamedType) {
            throw SerializationException::unsupportedParameter($class, $name);
        }
        $typeName = $type->getName();

        if ($typeName === \DateTimeImmutable::class) {
            if (!is_string($value)) {
                throw SerializationException::unexpectedValue($class, $name, 'date string');
            }
            return new \DateTimeImmutable($value);
        }
        if (!$type->isBuiltin()) {
            if (!is_array($value) || !class_exists($typeName)) {
                throw SerializationException::unexpectedValue($class, $name, 'object payload');
            }
            /** @var array<string, mixed> $value */
            return $this->fromArray($typeName, $value);
        }
        return match ($typeName) {
            'int' => is_int($value) ? $value : throw SerializationException::unexpectedValue($class, $name, 'int'),
            'string' => is_string($value) ? $value : throw SerializationException::unexpectedValue($class, $name, 'string'),
            default => throw SerializationException::unsupportedParameter($class, $name),
        };
    }

    /**
     * @param class-string $class
     * @return list<\ReflectionParameter>
     */
    private function parametersOf(string $class): array
    {
        return new \ReflectionClass($class)->getConstructor()?->getParameters() ?? [];
    }
}
