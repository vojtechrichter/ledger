<?php

declare(strict_types=1);

namespace Shared\Infrastructure\EventStore;

use Shared\Domain\DomainEventInterface;
use Shared\Domain\EventType;

final class EventRegistry
{
    /** @var array<class-string<DomainEventInterface>, EventType> */
    private array $byClass = [];

    /** @var array<string, class-string<DomainEventInterface>> */
    private array $byName = [];

    /**
     * @param list<class-string<DomainEventInterface>> $classes
     * @throws \ReflectionException
     */
    public function __construct(array $classes)
    {
        foreach ($classes as $class) {
            $attributes = new \ReflectionClass($class)->getAttributes(EventType::class);
            if ($attributes === []) {
                throw UnknownEventException::missingAttribute($class);
            }
            $type = $attributes[0]->newInstance();
            $this->byClass[$class] = $type;
            $this->byName[$type->name] = $class;
        }
    }

    public function nameOf(DomainEventInterface $event): string
    {
        return $this->typeOf($event)->name;
    }

    public function versionOf(DomainEventInterface $event): int
    {
        return $this->typeOf($event)->version;
    }

    /** @return class-string<DomainEventInterface> */
    public function classFor(string $name): string
    {
        return $this->byName[$name] ?? throw UnknownEventException::forName($name);
    }

    private function typeOf(DomainEventInterface $event): EventType
    {
        return $this->byClass[$event::class] ?? throw UnknownEventException::forClass($event::class);
    }
}
