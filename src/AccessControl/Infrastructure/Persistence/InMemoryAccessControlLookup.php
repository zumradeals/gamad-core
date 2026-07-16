<?php

declare(strict_types=1);

namespace Gamad\Core\AccessControl\Infrastructure\Persistence;

use Gamad\Core\AccessControl\Application\AccessControlLookup;

final class InMemoryAccessControlLookup implements AccessControlLookup
{
    /** @var array<string, true> */
    private array $persons = [];

    /** @var array<string, true> */
    private array $organizations = [];

    /** @var array<string, string> */
    private array $accounts = [];

    public function registerPerson(string $personId): void
    {
        $this->persons[$personId] = true;
    }

    public function registerOrganization(string $organizationId): void
    {
        $this->organizations[$organizationId] = true;
    }

    public function registerAccount(string $accountId, string $personId): void
    {
        $this->accounts[$accountId] = $personId;
    }

    public function personExists(string $personId): bool
    {
        return isset($this->persons[$personId]);
    }

    public function organizationExists(string $organizationId): bool
    {
        return isset($this->organizations[$organizationId]);
    }

    public function resolveAccountToPerson(string $accountId): ?string
    {
        return $this->accounts[$accountId] ?? null;
    }
}
