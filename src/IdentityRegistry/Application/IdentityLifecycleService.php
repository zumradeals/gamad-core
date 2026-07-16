<?php

declare(strict_types=1);

namespace Gamad\Core\IdentityRegistry\Application;

use Gamad\Core\IdentityRegistry\Domain\Identity;
use Gamad\Core\IdentityRegistry\Domain\IdentityId;
use Gamad\Core\IdentityRegistry\Domain\IdentityRepository;
use Gamad\Core\IdentityRegistry\Domain\IdentityStatus;
use Gamad\Core\Shared\Contract\AccessControlGateway;
use Gamad\Core\Shared\Contract\AccessDenied;
use Gamad\Core\Shared\Contract\IdentityId as ContractIdentityId;
use InvalidArgumentException;

final readonly class IdentityLifecycleService
{
    public function __construct(
        private IdentityRepository $identities,
        private AtomicIdentityPersister $persister,
        private AccessControlGateway $accessControl,
    ) {}

    public function transition(IdentityId $identityId, IdentityStatus $target, string $actorId): ?Identity
    {
        // No organizational context exists for an identity transition — the
        // identity itself is evaluated as context (ADR-0021 Task 8, same
        // rationale as RegisterIdentityHandler). ADR-0011 bootstrap actor
        // ids are not guaranteed to match ADR-0017's format; when they
        // don't, this checkpoint is skipped rather than crashing a flow it
        // does not otherwise gate (coexistence, GENESIS-013 §5.1).
        try {
            $context = new ContractIdentityId($identityId->value);
            $decision = $this->accessControl->can(new ContractIdentityId($actorId), 'identity:status:change', $context);
            if (!$decision->allowed) {
                throw AccessDenied::forDecision('identity:status:change', $decision->reason);
            }
        } catch (InvalidArgumentException) {
        }

        $identity = $this->identities->findById($identityId);
        if ($identity === null) {
            return null;
        }

        $identity->transitionTo($target);
        $this->persister->persist($identity);

        return $identity;
    }
}
