<?php

declare(strict_types=1);

namespace Gamad\Core\PersonsAndAccounts\Infrastructure\Persistence;

use Gamad\Core\PersonsAndAccounts\Domain\Person;
use Gamad\Core\PersonsAndAccounts\Domain\PersonId;
use Gamad\Core\PersonsAndAccounts\Domain\PersonRepository;

final class InMemoryPersonRepository implements PersonRepository
{
    /** @var array<string, Person> */
    private array $persons = [];

    public function save(Person $person): void
    {
        $this->persons[(string) $person->id()] = $person;
    }

    public function findById(PersonId $personId): ?Person
    {
        return $this->persons[(string) $personId] ?? null;
    }

    public function exists(PersonId $personId): bool
    {
        return isset($this->persons[(string) $personId]);
    }
}
