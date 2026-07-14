<?php

declare(strict_types=1);

namespace Gamad\Core\PersonsAndAccounts\Infrastructure\Persistence;

use DateTimeImmutable;
use Gamad\Core\PersonsAndAccounts\Domain\AuthenticationMethodId;
use Gamad\Core\PersonsAndAccounts\Domain\Session;
use Gamad\Core\PersonsAndAccounts\Domain\SessionId;
use Gamad\Core\PersonsAndAccounts\Domain\SessionRepository;
use Gamad\Core\PersonsAndAccounts\Domain\UserAccountId;
use PDO;

final readonly class PostgreSqlSessionRepository implements SessionRepository
{
    public function __construct(private PDO $connection)
    {
    }

    public function save(Session $session): void
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
            INSERT INTO sessions (id, user_account_id, authentication_method_id, token_hash, issued_at, expires_at, revoked_at)
            VALUES (:id, :user_account_id, :authentication_method_id, :token_hash, :issued_at, :expires_at, :revoked_at)
            ON CONFLICT (id) DO UPDATE SET revoked_at = EXCLUDED.revoked_at
            SQL
        );
        $statement->execute([
            'id' => (string) $session->id(),
            'user_account_id' => (string) $session->userAccountId(),
            'authentication_method_id' => (string) $session->authenticationMethodId(),
            'token_hash' => $session->tokenHash(),
            'issued_at' => $session->issuedAt()->format(DATE_ATOM),
            'expires_at' => $session->expiresAt()->format(DATE_ATOM),
            'revoked_at' => $session->revokedAt()?->format(DATE_ATOM),
        ]);
    }

    public function findById(SessionId $sessionId): ?Session
    {
        $statement = $this->connection->prepare(
            'SELECT id, user_account_id, authentication_method_id, token_hash, issued_at, expires_at, revoked_at FROM sessions WHERE id = :id'
        );
        $statement->execute(['id' => (string) $sessionId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->map($row);
    }

    public function findByTokenHash(string $tokenHash): ?Session
    {
        $statement = $this->connection->prepare(
            'SELECT id, user_account_id, authentication_method_id, token_hash, issued_at, expires_at, revoked_at FROM sessions WHERE token_hash = :token_hash'
        );
        $statement->execute(['token_hash' => $tokenHash]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->map($row);
    }

    public function findActiveByUserAccountId(UserAccountId $accountId): array
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
            SELECT id, user_account_id, authentication_method_id, token_hash, issued_at, expires_at, revoked_at
            FROM sessions
            WHERE user_account_id = :user_account_id
              AND revoked_at IS NULL
              AND expires_at > NOW()
            SQL
        );
        $statement->execute(['user_account_id' => (string) $accountId]);

        return array_map(fn (array $row): Session => $this->map($row), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @param array<string, mixed> $row */
    private function map(array $row): Session
    {
        return Session::reconstitute(
            id: new SessionId((string) $row['id']),
            userAccountId: new UserAccountId((string) $row['user_account_id']),
            authenticationMethodId: new AuthenticationMethodId((string) $row['authentication_method_id']),
            tokenHash: (string) $row['token_hash'],
            issuedAt: new DateTimeImmutable((string) $row['issued_at']),
            expiresAt: new DateTimeImmutable((string) $row['expires_at']),
            revokedAt: $row['revoked_at'] === null ? null : new DateTimeImmutable((string) $row['revoked_at']),
        );
    }
}
