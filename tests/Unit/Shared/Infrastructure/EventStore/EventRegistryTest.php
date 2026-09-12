<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\EventStore;

use Ledger\Domain\Account\AccountId;
use Ledger\Domain\Account\Event\AccountFrozen;
use Ledger\Domain\Account\Event\MoneyDeposited;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shared\Domain\DomainEventInterface;
use Shared\Domain\EventType;
use Shared\Domain\Money;
use Shared\Infrastructure\EventStore\EventRegistry;
use Shared\Infrastructure\EventStore\UnknownEventException;

#[CoversClass(EventRegistry::class)]
#[CoversClass(EventType::class)]
final class EventRegistryTest extends TestCase
{
    private EventRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new EventRegistry([MoneyDeposited::class, AccountFrozen::class]);
    }

    public function testResolvesNameAndVersionFromTheAttribute(): void
    {
        $event = new MoneyDeposited(AccountId::generate(), new Money(1, 'EUR'), new \DateTimeImmutable());

        self::assertSame('ledger.money_deposited', $this->registry->nameOf($event));
        self::assertSame(1, $this->registry->versionOf($event));
    }

    public function testResolvesClassFromName(): void
    {
        self::assertSame(AccountFrozen::class, $this->registry->classFor('ledger.account_frozen'));
    }

    public function testUnknownNameThrows(): void
    {
        $this->expectException(UnknownEventException::class);

        $this->registry->classFor('ledger.does_not_exist');
    }

    public function testUnregisteredClassThrows(): void
    {
        $this->expectException(UnknownEventException::class);

        $this->registry->nameOf(new class (new \DateTimeImmutable()) implements DomainEventInterface {
            public function __construct(public \DateTimeImmutable $occurredAt)
            {
            }
        });
    }

    public function testClassWithoutAttributeIsRejectedAtConstruction(): void
    {
        $this->expectException(UnknownEventException::class);

        new EventRegistry([UntaggedEvent::class]);
    }
}

final readonly class UntaggedEvent implements DomainEventInterface
{
    public function __construct(public \DateTimeImmutable $occurredAt)
    {
    }
}
