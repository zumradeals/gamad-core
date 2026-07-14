<?php

declare(strict_types=1);

namespace Gamad\Core\PersonsAndAccounts\Domain;

interface PersonRepository
{
    public function save(Person $person): void;

    public function findById(PersonId $personId): ?Person;

    public function exists(PersonId $personId): bool;
}
