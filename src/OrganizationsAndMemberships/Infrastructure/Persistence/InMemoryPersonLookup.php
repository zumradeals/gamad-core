<?php

declare(strict_types=1);

namespace Gamad\Core\OrganizationsAndMemberships\Infrastructure\Persistence;

use Gamad\Core\OrganizationsAndMemberships\Application\PersonLookup;

final class InMemoryPersonLookup implements PersonLookup
{
    /** @var array<string, true> */
    private array $persons = [];

    public function register(string $personId): void
    {
        $this->persons[$personId] = true;
    }

    public function exists(string $personId): bool
    {
        return isset($this->persons[$personId]);
    }
}
