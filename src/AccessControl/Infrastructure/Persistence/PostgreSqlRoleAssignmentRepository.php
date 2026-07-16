<?php

declare(strict_types=1);

namespace Gamad\Core\AccessControl\Infrastructure\Persistence;

use DateTimeImmutable;
use Gamad\Core\AccessControl\Domain\RoleAssignment;
use Gamad\Core\AccessControl\Domain\RoleAssignmentId;
use Gamad\Core\AccessControl\Domain\RoleAssignmentRepository;
use Gamad\Core\AccessControl\Domain\RoleAssignmentStatus;
use Gamad\Core\AccessControl\Domain\RoleId;
use PDO;

final readonly class PostgreSqlRoleAssignmentRepository implements RoleAssignmentRepository
{
    public function __construct(private PDO $connection)
    {
    }

    public function save(RoleAssignment $assignment): void
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
            INSERT INTO role_assignments (id, role_id, person_id, organization_id, status, assigned_at, revoked_at)
            VALUES (:id, :role_id, :person_id, :organization_id, :status, :assigned_at, :revoked_at)
            ON CONFLICT (id) DO UPDATE SET
                status = EXCLUDED.status,
                revoked_at = EXCLUDED.revoked_at
            SQL
        );
        $statement->execute([
            'id' => (string) $assignment->id(),
            'role_id' => (string) $assignment->roleId(),
            'person_id' => $assignment->personId(),
            'organization_id' => $assignment->organizationId(),
            'status' => $assignment->status()->value,
            'assigned_at' => $assignment->assignedAt()->format(DATE_ATOM),
            'revoked_at' => $assignment->revokedAt()?->format(DATE_ATOM),
        ]);
    }

    public function findById(RoleAssignmentId $id): ?RoleAssignment
    {
        $statement = $this->connection->prepare(
            'SELECT id, role_id, person_id, organization_id, status, assigned_at, revoked_at FROM role_assignments WHERE id = :id'
        );
        $statement->execute(['id' => (string) $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function findActive(RoleId $roleId, string $personId, string $organizationId): ?RoleAssignment
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
            SELECT id, role_id, person_id, organization_id, status, assigned_at, revoked_at
            FROM role_assignments
            WHERE role_id = :role_id AND person_id = :person_id AND organization_id = :organization_id AND status = 'active'
            SQL
        );
        $statement->execute([
            'role_id' => (string) $roleId,
            'person_id' => $personId,
            'organization_id' => $organizationId,
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function findActiveByPerson(string $personId): array
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
            SELECT id, role_id, person_id, organization_id, status, assigned_at, revoked_at
            FROM role_assignments
            WHERE person_id = :person_id AND status = 'active'
            ORDER BY assigned_at
            SQL
        );
        $statement->execute(['person_id' => $personId]);

        return array_map(
            fn (array $row): RoleAssignment => $this->hydrate($row),
            $statement->fetchAll(PDO::FETCH_ASSOC),
        );
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): RoleAssignment
    {
        return RoleAssignment::reconstitute(
            new RoleAssignmentId((string) $row['id']),
            new RoleId((string) $row['role_id']),
            (string) $row['person_id'],
            (string) $row['organization_id'],
            RoleAssignmentStatus::from((string) $row['status']),
            new DateTimeImmutable((string) $row['assigned_at']),
            $row['revoked_at'] === null ? null : new DateTimeImmutable((string) $row['revoked_at']),
        );
    }
}
