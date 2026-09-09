<?php

declare(strict_types=1);

namespace Ledger\Infrastructure\Persistence;

use Ledger\Application\Exception\AccountNotFoundException;
use Ledger\Application\Port\AccountRepositoryInterface;
use Ledger\Domain\Account\Account;
use Ledger\Domain\Account\AccountId;
use Shared\Infrastructure\EventStore\EventStoreInterface;

final readonly class EventSourcedAccountRepository implements AccountRepositoryInterface
{
    public function __construct(private EventStoreInterface $eventStore)
    {
    }

    #[\Override]
    public function get(AccountId $accountId): Account
    {
        $history = $this->eventStore->load($accountId->value);
        if ($history === []) {
            throw AccountNotFoundException::withId($accountId);
        }
        return Account::reconstitute($history);
    }

    #[\Override]
    public function save(Account $account): void
    {
        $expectedVersion = $account->version;
        $events = $account->releaseEvents();
        if ($events === []) {
            return;
        }
        (void) $this->eventStore->append($account->accountId->value, $expectedVersion, $events);
    }
}
