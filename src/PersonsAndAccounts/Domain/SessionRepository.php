<?php

declare(strict_types=1);

namespace Gamad\Core\PersonsAndAccounts\Domain;

interface SessionRepository
{
    public function save(Session $session): void;

    public function findById(SessionId $sessionId): ?Session;

    public function findByTokenHash(string $tokenHash): ?Session;

    /** @return list<Session> */
    public function findActiveByUserAccountId(UserAccountId $accountId): array;
}
