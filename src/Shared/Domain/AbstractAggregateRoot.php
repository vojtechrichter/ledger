<?php

declare(strict_types=1);

namespace Shared\Domain;

abstract class AbstractAggregateRoot
{
    /** @var list<DomainEventInterface> */
    private array $recordedEvents = [];

    public private(set) int $version = 0;

    final public function __construct() {}

    /**
     * @param iterable<DomainEventInterface> $history
     * @return static
     */
    public static function reconstitute(iterable $history): static
    {
       $self = new static();
       foreach ($history as $event) {
           $self->apply($event);
           $self->version++;
       }
       return $self;
    }

    /**
     * @return list<DomainEventInterface>
     */
    public function releaseEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];
        $this->version += count($events);
        return $events;
    }

    protected function recordThat(DomainEventInterface $event): void
    {
        $this->apply($event);
        $this->recordedEvents[] = $event;
    }

    abstract protected function apply(DomainEventInterface $event): void;
}
