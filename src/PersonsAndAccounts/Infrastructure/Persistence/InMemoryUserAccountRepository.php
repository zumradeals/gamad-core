<?php

declare(strict_types=1);

namespace Gamad\Core\PersonsAndAccounts\Infrastructure\Persistence;

use Gamad\Core\PersonsAndAccounts\Domain\PersonId;
use Gamad\Core\PersonsAndAccounts\Domain\UserAccount;
use Gamad\Core\PersonsAndAccounts\Domain\UserAccountId;
use Gamad\Core\PersonsAndAccounts\Domain\UserAccountRepository;

final class InMemoryUserAccountRepository implements UserAccountRepository
{
    /** @var array<string, UserAccount> */
    private array $accounts = [];

    public function save(UserAccount $account): void
    {
        $this->accounts[(string) $account->id()] = $account;
    }

    public function findById(UserAccountId $accountId): ?UserAccount
    {
        return $this->accounts[(string) $accountId] ?? null;
    }

    public function findByPersonId(PersonId $personId): ?UserAccount
    {
        foreach ($this->accounts as $account) {
            if ($account->personId()->equals($personId)) {
                return $account;
            }
        }

        return null;
    }

    public function existsForPerson(PersonId $personId): bool
    {
        return $this->findByPersonId($personId) !== null;
    }
}
