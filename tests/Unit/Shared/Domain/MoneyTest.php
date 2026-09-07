<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\CurrencyMismatchException;
use Shared\Domain\Money;

#[CoversClass(Money::class)]
final class MoneyTest extends TestCase
{
    public function testAddReturnsSumInSameCurrency(): void
    {
        $sum = new Money(100, 'EUR')->add(new Money(50, 'EUR'));

        self::assertSame(150, $sum->amount);
        self::assertSame('EUR', $sum->currency);
    }

    public function testSubtractReturnsDifferenceInSameCurrency(): void
    {
        $difference = new Money(100, 'EUR')->subtract(new Money(30, 'EUR'));

        self::assertSame(70, $difference->amount);
        self::assertSame('EUR', $difference->currency);
    }

    public function testSubtractCanGoNegative(): void
    {
        $difference = new Money(30, 'EUR')->subtract(new Money(100, 'EUR'));

        self::assertSame(-70, $difference->amount);
        self::assertTrue($difference->isNegative());
    }

    public function testArithmeticDoesNotMutateOperands(): void
    {
        $left = new Money(100, 'EUR');
        $right = new Money(50, 'EUR');

        (void) $left->add($right);
        (void) $left->subtract($right);

        self::assertSame(100, $left->amount);
        self::assertSame(50, $right->amount);
    }

    public function testIsNegativeIsFalseForZeroAndPositive(): void
    {
        self::assertFalse(new Money(0, 'EUR')->isNegative());
        self::assertFalse(new Money(1, 'EUR')->isNegative());
    }

    public function testEqualsRequiresSameAmountAndCurrency(): void
    {
        $money = new Money(100, 'EUR');

        self::assertTrue($money->equals(new Money(100, 'EUR')));
        self::assertFalse($money->equals(new Money(101, 'EUR')));
        self::assertFalse($money->equals(new Money(100, 'USD')));
    }

    public function testAddThrowsOnCurrencyMismatch(): void
    {
        $this->expectException(CurrencyMismatchException::class);

        (void) new Money(100, 'EUR')->add(new Money(100, 'USD'));
    }

    public function testSubtractThrowsOnCurrencyMismatch(): void
    {
        $this->expectException(CurrencyMismatchException::class);

        (void) new Money(100, 'EUR')->subtract(new Money(100, 'USD'));
    }
}
