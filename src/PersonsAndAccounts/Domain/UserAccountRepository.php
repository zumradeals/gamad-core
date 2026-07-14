<?php

declare(strict_types=1);

namespace Gamad\Core\PersonsAndAccounts\Domain;

interface UserAccountRepository
{
    public function save(UserAccount $account): void;

    public function findById(UserAccountId $accountId): ?UserAccount;

    public function findByPersonId(PersonId $personId): ?UserAccount;

    public function existsForPerson(PersonId $personId): bool;
}
