<?php

declare(strict_types=1);

namespace Gamad\Core\IdentityRegistry\Application\Command;

use Gamad\Core\IdentityRegistry\Application\Exception\IdentityAlreadyExists;
use Gamad\Core\IdentityRegistry\Domain\Identity;
use Gamad\Core\IdentityRegistry\Domain\IdentityRepository;

final readonly class RegisterIdentityHandler
{
    public function __construct(private IdentityRepository $repository)
    {
    }

    public function __invoke(RegisterIdentity $command): Identity
    {
        if ($this->repository->exists($command->identityId)) {
            throw IdentityAlreadyExists::withId((string) $command->identityId);
        }

        $identity = Identity::register(
            id: $command->identityId,
            type: $command->identityType,
            registeredAt: $command->registeredAt,
        );

        $this->repository->save($identity);

        return $identity;
    }
}
