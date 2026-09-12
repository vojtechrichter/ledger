<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\EventStore;

use Ledger\Domain\Account\AccountId;
use Ledger\Domain\Account\Event\AccountClosed;
use Ledger\Domain\Account\Event\AccountFrozen;
use Ledger\Domain\Account\Event\AccountOpened;
use Ledger\Domain\Account\Event\AccountUnfrozen;
use Ledger\Domain\Account\Event\HoldCaptured;
use Ledger\Domain\Account\Event\HoldPlaced;
use Ledger\Domain\Account\Event\HoldReleased;
use Ledger\Domain\Account\Event\MoneyDeposited;
use Ledger\Domain\Account\Event\MoneyWithdrawn;
use Ledger\Domain\Account\HoldId;
use Ledger\Domain\Account\OwnerId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Domain\DomainEventInterface;
use Shared\Domain\Money;
use Shared\Infrastructure\EventStore\EventSerializer;
use Shared\Infrastructure\EventStore\SerializationException;

#[CoversClass(EventSerializer::class)]
final class EventSerializerTest extends TestCase
{
    private EventSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new EventSerializer();
    }

    public function testMoneyDepositedPayloadShape(): void
    {
        $event = new MoneyDeposited(
            AccountId::fromString('0199270e-6b1a-7c4e-9f3a-1c2d3e4f5a6b'),
            new Money(1250, 'EUR'),
            new \DateTimeImmutable('2026-09-10 08:30:15.123456+02:00'),
        );

        self::assertSame([
            'accountId' => ['value' => '0199270e-6b1a-7c4e-9f3a-1c2d3e4f5a6b'],
            'amount' => ['amount' => 1250, 'currency' => 'EUR'],
            'occurredAt' => '2026-09-10T08:30:15.123456+02:00',
        ], $this->serializer->serialize($event));
    }

    /** @return iterable<string, array{DomainEventInterface}> */
    public static function events(): iterable
    {
        $accountId = AccountId::generate();
        $holdId = HoldId::generate();
        $at = new \DateTimeImmutable('2026-09-10 08:30:15.123456+02:00');

        yield 'AccountOpened' => [new AccountOpened($accountId, OwnerId::generate(), 'EUR', $at)];
        yield 'MoneyDeposited' => [new MoneyDeposited($accountId, new Money(100, 'EUR'), $at)];
        yield 'MoneyWithdrawn' => [new MoneyWithdrawn($accountId, new Money(100, 'EUR'), $at)];
        yield 'HoldPlaced' => [new HoldPlaced($accountId, $holdId, new Money(100, 'EUR'), $at)];
        yield 'HoldReleased' => [new HoldReleased($accountId, $holdId, $at)];
        yield 'HoldCaptured' => [new HoldCaptured($accountId, $holdId, $at)];
        yield 'AccountFrozen' => [new AccountFrozen($accountId, $at)];
        yield 'AccountUnfrozen' => [new AccountUnfrozen($accountId, $at)];
        yield 'AccountClosed' => [new AccountClosed($accountId, $at)];
    }

    #[DataProvider('events')]
    public function testEveryEventSurvivesAJsonRoundTrip(DomainEventInterface $event): void
    {
        $json = json_encode($this->serializer->serialize($event), JSON_THROW_ON_ERROR);
        /** @var array<string, mixed> $payload */
        $payload = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        $restored = $this->serializer->deserialize($event::class, $payload);

        self::assertEquals($event, $restored);
    }

    public function testTimestampKeepsMicrosecondsAndOffset(): void
    {
        $at = new \DateTimeImmutable('2026-09-10 08:30:15.123456+02:00');
        $event = new AccountFrozen(AccountId::generate(), $at);

        $restored = $this->serializer->deserialize(AccountFrozen::class, $this->serializer->serialize($event));

        self::assertInstanceOf(AccountFrozen::class, $restored);
        self::assertSame('2026-09-10T08:30:15.123456+02:00', $restored->occurredAt->format('Y-m-d\TH:i:s.uP'));
    }

    public function testMissingFieldThrows(): void
    {
        $this->expectException(SerializationException::class);

        $this->serializer->deserialize(AccountFrozen::class, ['accountId' => ['value' => 'x']]);
    }

    public function testWrongScalarTypeThrows(): void
    {
        $this->expectException(SerializationException::class);

        $this->serializer->deserialize(MoneyDeposited::class, [
            'accountId' => ['value' => 'x'],
            'amount' => ['amount' => '100', 'currency' => 'EUR'],
            'occurredAt' => '2026-09-10T08:30:15.000000+00:00',
        ]);
    }

    public function testNonArrayForObjectFieldThrows(): void
    {
        $this->expectException(SerializationException::class);

        $this->serializer->deserialize(AccountFrozen::class, [
            'accountId' => 'not-an-object',
            'occurredAt' => '2026-09-10T08:30:15.000000+00:00',
        ]);
    }

    public function testNonStringTimestampThrows(): void
    {
        $this->expectException(SerializationException::class);

        $this->serializer->deserialize(AccountFrozen::class, [
            'accountId' => ['value' => 'x'],
            'occurredAt' => 1757485815,
        ]);
    }

    public function testUnionTypedParameterCannotBeDeserialized(): void
    {
        $this->expectException(SerializationException::class);

        $this->serializer->deserialize(EventWithUnionType::class, ['value' => 1, 'occurredAt' => '2026-09-10T08:30:15.000000+00:00']);
    }

    public function testUnsupportedParameterTypeThrows(): void
    {
        $this->expectException(SerializationException::class);

        $this->serializer->serialize(new EventWithArray(['a'], new \DateTimeImmutable()));
    }
}

final readonly class EventWithUnionType implements DomainEventInterface
{
    public function __construct(public int|string $value, public \DateTimeImmutable $occurredAt)
    {
    }
}

final readonly class EventWithArray implements DomainEventInterface
{
    /** @param list<string> $items */
    public function __construct(public array $items, public \DateTimeImmutable $occurredAt)
    {
    }
}
