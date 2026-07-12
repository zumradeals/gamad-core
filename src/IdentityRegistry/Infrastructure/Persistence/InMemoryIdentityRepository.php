<?php

declare(strict_types=1);

namespace Gamad\Core\IdentityRegistry\Infrastructure\Persistence;

use Gamad\Core\IdentityRegistry\Domain\Identity;
use Gamad\Core\IdentityRegistry\Domain\IdentityId;
use Gamad\Core\IdentityRegistry\Domain\IdentityRepository;

final class InMemoryIdentityRepository implements IdentityRepository
{
    /** @var array<string, Identity> */
    private array $identities = [];

    public function save(Identity $identity): void
    {
        $this->identities[(string) $identity->id()] = $identity;
    }

    public function findById(IdentityId $identityId): ?Identity
    {
        return $this->identities[(string) $identityId] ?? null;
    }

    public function exists(IdentityId $identityId): bool
    {
        return isset($this->identities[(string) $identityId]);
    }
}
