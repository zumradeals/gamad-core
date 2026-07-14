<?php

declare(strict_types=1);

namespace Gamad\Core\PersonsAndAccounts\Infrastructure\Persistence;

use Gamad\Core\PersonsAndAccounts\Application\IdentityLookup;
use Gamad\Core\PersonsAndAccounts\Application\IdentityLookupResult;

final class InMemoryIdentityLookup implements IdentityLookup
{
    /** @var array<string, IdentityLookupResult> */
    private array $identities = [];

    public function register(string $identityId, string $type, string $status): void
    {
        $this->identities[$identityId] = new IdentityLookupResult($identityId, $type, $status);
    }

    public function find(string $identityId): ?IdentityLookupResult
    {
        return $this->identities[$identityId] ?? null;
    }
}
