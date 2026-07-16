<?php

declare(strict_types=1);

namespace Gamad\Core\PersonsAndAccounts\Application\Command;

use Gamad\Core\PersonsAndAccounts\Application\AtomicSessionPersister;
use Gamad\Core\PersonsAndAccounts\Application\Exception\SessionNotFound;
use Gamad\Core\PersonsAndAccounts\Domain\SessionId;
use Gamad\Core\PersonsAndAccounts\Domain\SessionRepository;
use Gamad\Core\Shared\Contract\AccessControlGateway;
use Gamad\Core\Shared\Contract\AccessDenied;
use Gamad\Core\Shared\Contract\IdentityId as ContractIdentityId;
use InvalidArgumentException;

final readonly class RevokeSessionHandler
{
    public function __construct(
        private SessionRepository $sessions,
        private AtomicSessionPersister $persister,
        private AccessControlGateway $accessControl,
    ) {
    }

    public function __invoke(RevokeSession $command): void
    {
        $session = $this->sessions->findById(new SessionId($command->sessionId));
        if ($session === null) {
            throw SessionNotFound::withId($command->sessionId);
        }

        // No person-scoped context is available for a session by itself
        // (a session belongs to a UserAccountId, not a person GAM- identity
        // — resolving that mapping is Shared\Contract\AccessControlGateway
        // callers' job elsewhere, e.g. AccessControlHttpController); the
        // session's own account id is used as a same-format placeholder for
        // both actor and context when none is supplied (ADR-0021 Task 8).
        try {
            $actor = new ContractIdentityId($command->actorId ?? (string) $session->userAccountId());
            $decision = $this->accessControl->can($actor, 'session:revoke', $actor);
            if (!$decision->allowed) {
                throw AccessDenied::forDecision('session:revoke', $decision->reason);
            }
        } catch (InvalidArgumentException) {
        }

        $session->revoke($command->reason);
        $this->persister->persist($session);
    }
}
