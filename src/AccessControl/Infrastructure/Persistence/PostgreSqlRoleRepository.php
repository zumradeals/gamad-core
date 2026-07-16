<?php

declare(strict_types=1);

namespace Gamad\Core\AccessControl\Infrastructure\Persistence;

use DateTimeImmutable;
use Gamad\Core\AccessControl\Domain\PermissionId;
use Gamad\Core\AccessControl\Domain\Role;
use Gamad\Core\AccessControl\Domain\RoleId;
use Gamad\Core\AccessControl\Domain\RoleRepository;
use Gamad\Core\AccessControl\Domain\RoleScope;
use Gamad\Core\AccessControl\Domain\RoleStatus;
use PDO;

final readonly class PostgreSqlRoleRepository implements RoleRepository
{
    public function __construct(private PDO $connection)
    {
    }

    public function save(Role $role): void
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
            INSERT INTO roles (id, name, scope, status, created_at)
            VALUES (:id, :name, :scope, :status, :created_at)
            ON CONFLICT (id) DO UPDATE SET status = EXCLUDED.status
            SQL
        );
        $statement->execute([
            'id' => (string) $role->id(),
            'name' => $role->name(),
            'scope' => $role->scope()->value,
            'status' => $role->status()->value,
            'created_at' => $role->createdAt()->format(DATE_ATOM),
        ]);

        $permissionStatement = $this->connection->prepare(
            <<<'SQL'
            INSERT INTO role_permissions (role_id, permission_id)
            VALUES (:role_id, :permission_id)
            ON CONFLICT DO NOTHING
            SQL
        );
        foreach ($role->permissionIds() as $permissionId) {
            $permissionStatement->execute([
                'role_id' => (string) $role->id(),
                'permission_id' => (string) $permissionId,
            ]);
        }
    }

    public function findById(RoleId $id): ?Role
    {
        $statement = $this->connection->prepare('SELECT id, name, scope, status, created_at FROM roles WHERE id = :id');
        $statement->execute(['id' => (string) $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function findByName(string $name): ?Role
    {
        $statement = $this->connection->prepare('SELECT id, name, scope, status, created_at FROM roles WHERE name = :name');
        $statement->execute(['name' => $name]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function findAll(): array
    {
        $statement = $this->connection->query('SELECT id, name, scope, status, created_at FROM roles ORDER BY name');

        return array_map(
            fn (array $row): Role => $this->hydrate($row),
            $statement->fetchAll(PDO::FETCH_ASSOC),
        );
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): Role
    {
        $permissionStatement = $this->connection->prepare('SELECT permission_id FROM role_permissions WHERE role_id = :role_id ORDER BY permission_id');
        $permissionStatement->execute(['role_id' => (string) $row['id']]);

        $permissionIds = array_map(
            static fn (string $permissionId): PermissionId => new PermissionId($permissionId),
            $permissionStatement->fetchAll(PDO::FETCH_COLUMN),
        );

        return Role::reconstitute(
            new RoleId((string) $row['id']),
            (string) $row['name'],
            RoleScope::from((string) $row['scope']),
            RoleStatus::from((string) $row['status']),
            new DateTimeImmutable((string) $row['created_at']),
            $permissionIds,
        );
    }
}
