<?php

declare(strict_types=1);

namespace Gamad\Core\IdentityRegistry\Domain;

interface IdentityRepository
{
    public function save(Identity $identity): void;

    public function findById(IdentityId $identityId): ?Identity;

    public function exists(IdentityId $identityId): bool;
}
