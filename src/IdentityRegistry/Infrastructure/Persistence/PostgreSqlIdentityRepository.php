<?php

declare(strict_types=1);

namespace Gamad\Core\IdentityRegistry\Infrastructure\Persistence;

use DateTimeImmutable;
use Gamad\Core\IdentityRegistry\Domain\Identity;
use Gamad\Core\IdentityRegistry\Domain\IdentityId;
use Gamad\Core\IdentityRegistry\Domain\IdentityInternalId;
use Gamad\Core\IdentityRegistry\Domain\IdentityRepository;
use Gamad\Core\IdentityRegistry\Domain\IdentityStatus;
use Gamad\Core\IdentityRegistry\Domain\IdentityType;
use PDO;

final readonly class PostgreSqlIdentityRepository implements IdentityRepository
{
    public function __construct(private PDO $connection) {}

    public function save(Identity $identity): void
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
            INSERT INTO identities (internal_id, id, type, status, registered_at)
            VALUES (:internal_id, :id, :type, :status, :registered_at)
            ON CONFLICT (id) DO UPDATE SET
                status = EXCLUDED.status
            SQL
        );

        $statement->execute([
            'internal_id' => (string) $identity->internalId(),
            'id' => (string) $identity->id(),
            'type' => $identity->type()->value,
            'status' => $identity->status()->value,
            'registered_at' => $identity->registeredAt()->format(DATE_ATOM),
        ]);
    }

    public function findById(IdentityId $identityId): ?Identity
    {
        $statement = $this->connection->prepare(
            'SELECT internal_id, id, type, status, registered_at FROM identities WHERE id = :id'
        );
        $statement->execute(['id' => (string) $identityId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return Identity::reconstitute(
            internalId: new IdentityInternalId((string) $row['internal_id']),
            id: new IdentityId((string) $row['id']),
            type: IdentityType::from((string) $row['type']),
            status: IdentityStatus::from((string) $row['status']),
            registeredAt: new DateTimeImmutable((string) $row['registered_at']),
        );
    }

    public function exists(IdentityId $identityId): bool
    {
        $statement = $this->connection->prepare('SELECT EXISTS(SELECT 1 FROM identities WHERE id = :id)');
        $statement->execute(['id' => (string) $identityId]);

        return (bool) $statement->fetchColumn();
    }
}
