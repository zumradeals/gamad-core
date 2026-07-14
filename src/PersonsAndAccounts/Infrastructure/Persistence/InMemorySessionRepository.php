<?php

declare(strict_types=1);

namespace Gamad\Core\PersonsAndAccounts\Infrastructure\Persistence;

use Gamad\Core\PersonsAndAccounts\Domain\Session;
use Gamad\Core\PersonsAndAccounts\Domain\SessionId;
use Gamad\Core\PersonsAndAccounts\Domain\SessionRepository;
use Gamad\Core\PersonsAndAccounts\Domain\UserAccountId;

final class InMemorySessionRepository implements SessionRepository
{
    /** @var array<string, Session> */
    private array $sessions = [];

    public function save(Session $session): void
    {
        $this->sessions[(string) $session->id()] = $session;
    }

    public function findById(SessionId $sessionId): ?Session
    {
        return $this->sessions[(string) $sessionId] ?? null;
    }

    public function findByTokenHash(string $tokenHash): ?Session
    {
        foreach ($this->sessions as $session) {
            if (hash_equals($session->tokenHash(), $tokenHash)) {
                return $session;
            }
        }

        return null;
    }

    public function findActiveByUserAccountId(UserAccountId $accountId): array
    {
        return array_values(array_filter(
            $this->sessions,
            static fn (Session $session): bool => $session->userAccountId()->equals($accountId) && $session->isActive(),
        ));
    }
}
