<?php

declare(strict_types=1);

namespace Gamad\Core\PersonsAndAccounts\Application\Command;

use Gamad\Core\PersonsAndAccounts\Application\AtomicSessionPersister;
use Gamad\Core\PersonsAndAccounts\Application\Exception\SessionNotFound;
use Gamad\Core\PersonsAndAccounts\Domain\SessionId;
use Gamad\Core\PersonsAndAccounts\Domain\SessionRepository;

final readonly class RevokeSessionHandler
{
    public function __construct(
        private SessionRepository $sessions,
        private AtomicSessionPersister $persister,
    ) {
    }

    public function __invoke(RevokeSession $command): void
    {
        $session = $this->sessions->findById(new SessionId($command->sessionId));
        if ($session === null) {
            throw SessionNotFound::withId($command->sessionId);
        }

        $session->revoke($command->reason);
        $this->persister->persist($session);
    }
}
