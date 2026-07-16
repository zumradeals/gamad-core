<?php

declare(strict_types=1);

namespace Gamad\Core\IdentityRegistry\Application\Command;

use Gamad\Core\IdentityRegistry\Application\AtomicIdentityPersister;
use Gamad\Core\IdentityRegistry\Application\CoreRealmProvider;
use Gamad\Core\IdentityRegistry\Application\IdentityIdentifierAuthority;
use Gamad\Core\IdentityRegistry\Application\IdentityRegistrationPolicy;
use Gamad\Core\IdentityRegistry\Domain\Identity;
use Gamad\Core\Shared\Contract\AccessControlGateway;
use Gamad\Core\Shared\Contract\AccessDenied;
use Gamad\Core\Shared\Contract\IdentityId as ContractIdentityId;
use Gamad\Core\Shared\Metrics\MetricsCollector;
use InvalidArgumentException;

final readonly class RegisterIdentityHandler
{
    public function __construct(
        private IdentityIdentifierAuthority $identifiers,
        private IdentityRegistrationPolicy $policy,
        private AtomicIdentityPersister $persister,
        private MetricsCollector $metrics,
        private CoreRealmProvider $realm,
        private AccessControlGateway $accessControl,
    ) {}

    public function __invoke(RegisterIdentity $command): Identity
    {
        // No organizational context exists yet at this point (registering an
        // identity precedes any Organizations and Memberships structure) —
        // the actor is evaluated against itself as context (ADR-0021 Task 2).
        // ADR-0011 bootstrap actor ids predate ADR-0017's realm-tagged format
        // and are not guaranteed to match it; when they don't, this
        // checkpoint cannot yet be evaluated and is skipped rather than
        // crashing a flow it does not otherwise gate (coexistence,
        // GENESIS-013 §5.1 / GENESIS-014 §E).
        try {
            $actor = new ContractIdentityId($command->actorId);
            $decision = $this->accessControl->can($actor, 'identity:create', $actor);
            if (!$decision->allowed) {
                throw AccessDenied::forDecision('identity:create', $decision->reason);
            }
        } catch (InvalidArgumentException) {
        }

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
