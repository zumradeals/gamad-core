<?php

declare(strict_types=1);

namespace Gamad\Core\OrganizationsAndMemberships\Infrastructure\Persistence;

use DateTimeImmutable;
use Gamad\Core\OrganizationsAndMemberships\Domain\Department;
use Gamad\Core\OrganizationsAndMemberships\Domain\DepartmentId;
use Gamad\Core\OrganizationsAndMemberships\Domain\DepartmentStatus;
use Gamad\Core\OrganizationsAndMemberships\Domain\Organization;
use Gamad\Core\OrganizationsAndMemberships\Domain\OrganizationId;
use Gamad\Core\OrganizationsAndMemberships\Domain\OrganizationRepository;
use Gamad\Core\OrganizationsAndMemberships\Domain\OrganizationStatus;
use PDO;

final readonly class PostgreSqlOrganizationRepository implements OrganizationRepository
{
    public function __construct(private PDO $connection)
    {
    }

    public function save(Organization $organization): void
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
            INSERT INTO organizations (identity_id, parent_id, name, status, founded_at)
            VALUES (:identity_id, :parent_id, :name, :status, :founded_at)
            ON CONFLICT (identity_id) DO UPDATE SET
                name = EXCLUDED.name,
                status = EXCLUDED.status
            SQL
        );
        $statement->execute([
            'identity_id' => (string) $organization->id(),
            'parent_id' => $organization->parentId() !== null ? (string) $organization->parentId() : null,
            'name' => $organization->name(),
            'status' => $organization->status()->value,
            'founded_at' => $organization->foundedAt()->format(DATE_ATOM),
        ]);

        $departmentStatement = $this->connection->prepare(
            <<<'SQL'
            INSERT INTO departments (id, organization_id, name, status)
            VALUES (:id, :organization_id, :name, :status)
            ON CONFLICT (id) DO UPDATE SET
                name = EXCLUDED.name,
                status = EXCLUDED.status
            SQL
        );
        foreach ($organization->departments() as $department) {
            $departmentStatement->execute([
                'id' => (string) $department->id(),
                'organization_id' => (string) $organization->id(),
                'name' => $department->name(),
                'status' => $department->status()->value,
            ]);
        }
    }

    public function findById(OrganizationId $organizationId): ?Organization
    {
        $statement = $this->connection->prepare(
            'SELECT identity_id, parent_id, name, status, founded_at FROM organizations WHERE identity_id = :identity_id'
        );
        $statement->execute(['identity_id' => (string) $organizationId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->map($row);
    }

    public function exists(OrganizationId $organizationId): bool
    {
        $statement = $this->connection->prepare('SELECT EXISTS(SELECT 1 FROM organizations WHERE identity_id = :identity_id)');
        $statement->execute(['identity_id' => (string) $organizationId]);

        return (bool) $statement->fetchColumn();
    }

    public function findChildren(OrganizationId $parentId): array
    {
        $statement = $this->connection->prepare('SELECT identity_id FROM organizations WHERE parent_id = :parent_id ORDER BY identity_id');
        $statement->execute(['parent_id' => (string) $parentId]);

        $children = [];
        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $identityId) {
            $child = $this->findById(new OrganizationId((string) $identityId));
            if ($child !== null) {
                $children[] = $child;
            }
        }

        return $children;
    }

    /** @param array<string, mixed> $row */
    private function map(array $row): Organization
    {
        $departmentStatement = $this->connection->prepare(
            'SELECT id, name, status FROM departments WHERE organization_id = :organization_id ORDER BY id'
        );
        $departmentStatement->execute(['organization_id' => (string) $row['identity_id']]);

        $departments = [];
        foreach ($departmentStatement->fetchAll(PDO::FETCH_ASSOC) as $departmentRow) {
            $departments[] = Department::reconstitute(
                new DepartmentId((string) $departmentRow['id']),
                (string) $departmentRow['name'],
                DepartmentStatus::from((string) $departmentRow['status']),
            );
        }

        return Organization::reconstitute(
            id: new OrganizationId((string) $row['identity_id']),
            parentId: $row['parent_id'] === null ? null : new OrganizationId((string) $row['parent_id']),
            name: (string) $row['name'],
            status: OrganizationStatus::from((string) $row['status']),
            foundedAt: new DateTimeImmutable((string) $row['founded_at']),
            departments: $departments,
        );
    }
}
