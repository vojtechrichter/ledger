<?php

declare(strict_types=1);

namespace Shared\Domain\Exception;

final class CurrencyMismatchException extends \RuntimeException
{
    public function __construct(string $currency1, string $currency2)
    {
        parent::__construct(sprintf("Cannot add amounts between currency `%s` and `%s`.", $currency1, $currency2));
    }
}
