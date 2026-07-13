<?php

declare(strict_types=1);

namespace Gamad\Core\IdentityRegistry\Infrastructure\Policy;

use DomainException;
use Gamad\Core\IdentityRegistry\Application\IdentityRegistrationPolicy;
use Gamad\Core\IdentityRegistry\Domain\IdentityType;

final readonly class AllowConfiguredIdentityTypesPolicy implements IdentityRegistrationPolicy
{
    /** @param list<IdentityType> $allowedTypes */
    public function __construct(private array $allowedTypes = []) {}

    public function assertAllowed(string $actorId, IdentityType $type): void
    {
        if ($actorId === '') {
            throw new DomainException('An authenticated actor is required to register an identity.');
        }

        if ($this->allowedTypes !== [] && !in_array($type, $this->allowedTypes, true)) {
            throw new DomainException(sprintf('Registration of identity type %s is not allowed.', $type->value));
        }
    }
}
