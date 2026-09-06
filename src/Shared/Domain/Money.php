<?php

declare(strict_types=1);

namespace Shared\Domain;

final readonly class Money
{
    public function __construct(
        public int $amount,
        public string $currency,
    ) {}

    #[\NoDiscard]
    public function add(self $other): self
    {
        $this->assertMatchingCurrency($other);
        return new self($this->amount + $other->amount, $other->currency);
    }

    #[\NoDiscard]
    public function subtract(self $other): self
    {
        $this->assertMatchingCurrency($other);
        return new self($this->amount - $other->amount, $other->currency);
    }

    public function isNegative(): bool
    {
        return $this->amount < 0;
    }

    public function equals(Money $other): bool
    {
        return ($this->amount === $other->amount) && ($this->currency === $other->currency);
    }

    private function assertMatchingCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new \Shared\Domain\Exception\CurrencyMismatchException($this->currency, $other->currency);
        }
    }
}
