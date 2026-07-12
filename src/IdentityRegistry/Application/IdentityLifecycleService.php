<?php

declare(strict_types=1);

namespace Gamad\Core\IdentityRegistry\Application;

use Gamad\Core\IdentityRegistry\Domain\Identity;
use Gamad\Core\IdentityRegistry\Domain\IdentityId;
use Gamad\Core\IdentityRegistry\Domain\IdentityRepository;
use Gamad\Core\IdentityRegistry\Domain\IdentityStatus;

final readonly class IdentityLifecycleService
{
    public function __construct(
        private IdentityRepository $identities,
        private AtomicIdentityPersister $persister,
    ) {}

    public function transition(IdentityId $identityId, IdentityStatus $target): ?Identity
    {
        $identity = $this->identities->findById($identityId);
        if ($identity === null) {
            return null;
        }

        $identity->transitionTo($target);
        $this->persister->persist($identity);

        return $identity;
    }
}
