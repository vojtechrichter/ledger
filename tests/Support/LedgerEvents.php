<?php

declare(strict_types=1);

namespace Tests\Support;

use Ledger\Domain\Account\Event\AccountClosed;
use Ledger\Domain\Account\Event\AccountFrozen;
use Ledger\Domain\Account\Event\AccountOpened;
use Ledger\Domain\Account\Event\AccountUnfrozen;
use Ledger\Domain\Account\Event\HoldCaptured;
use Ledger\Domain\Account\Event\HoldPlaced;
use Ledger\Domain\Account\Event\HoldReleased;
use Ledger\Domain\Account\Event\MoneyDeposited;
use Ledger\Domain\Account\Event\MoneyWithdrawn;
use Shared\Infrastructure\EventStore\EventRegistry;

final class LedgerEvents
{
    public static function registry(): EventRegistry
    {
        return new EventRegistry([
            AccountOpened::class,
            MoneyDeposited::class,
            MoneyWithdrawn::class,
            HoldPlaced::class,
            HoldReleased::class,
            HoldCaptured::class,
            AccountFrozen::class,
            AccountUnfrozen::class,
            AccountClosed::class,
        ]);
    }
}
