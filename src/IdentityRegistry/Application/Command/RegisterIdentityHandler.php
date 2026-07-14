<?php

declare(strict_types=1);

namespace Gamad\Core\IdentityRegistry\Application\Command;

use Gamad\Core\IdentityRegistry\Application\AtomicIdentityPersister;
use Gamad\Core\IdentityRegistry\Application\CoreRealmProvider;
use Gamad\Core\IdentityRegistry\Application\IdentityIdentifierAuthority;
use Gamad\Core\IdentityRegistry\Application\IdentityRegistrationPolicy;
use Gamad\Core\IdentityRegistry\Domain\Identity;
use Gamad\Core\Shared\Metrics\MetricsCollector;

final readonly class RegisterIdentityHandler
{
    public function __construct(
        private IdentityIdentifierAuthority $identifiers,
        private IdentityRegistrationPolicy $policy,
        private AtomicIdentityPersister $persister,
        private MetricsCollector $metrics,
        private CoreRealmProvider $realm,
    ) {}

    public function __invoke(RegisterIdentity $command): Identity
    {
        $this->policy->assertAllowed($command->actorId, $command->identityType);
        $allocated = $this->identifiers->allocate($command->identityType, $this->realm->realm());

        $identity = Identity::register(
            internalId: $allocated->internalId,
            id: $allocated->publicId,
            type: $command->identityType,
            registeredAt: $command->registeredAt,
        );

        $this->persister->persist($identity);
        $this->metrics->increment('gamad_identity_registered_total', 1, [
            'identity_type' => $command->identityType->value,
        ]);

        return $identity;
    }
}
