<?php

declare(strict_types=1);

namespace Gamad\Core\AccessControl\Infrastructure\Persistence;

use Gamad\Core\AccessControl\Domain\Permission;
use Gamad\Core\AccessControl\Domain\PermissionId;
use Gamad\Core\AccessControl\Domain\PermissionRepository;
use PDO;

final readonly class PostgreSqlPermissionRepository implements PermissionRepository
{
    public function __construct(private PDO $connection)
    {
    }

    public function save(Permission $permission): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO permissions (id, name, description) VALUES (:id, :name, :description)'
        );
        $statement->execute([
            'id' => (string) $permission->id,
            'name' => $permission->name,
            'description' => $permission->description,
        ]);
    }

    public function findById(PermissionId $id): ?Permission
    {
        $statement = $this->connection->prepare('SELECT id, name, description FROM permissions WHERE id = :id');
        $statement->execute(['id' => (string) $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function findByName(string $name): ?Permission
    {
        $statement = $this->connection->prepare('SELECT id, name, description FROM permissions WHERE name = :name');
        $statement->execute(['name' => $name]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function findAll(): array
    {
        $statement = $this->connection->query('SELECT id, name, description FROM permissions ORDER BY name');

        return array_map(
            fn (array $row): Permission => $this->hydrate($row),
            $statement->fetchAll(PDO::FETCH_ASSOC),
        );
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): Permission
    {
        return new Permission(new PermissionId((string) $row['id']), (string) $row['name'], (string) $row['description']);
    }
}
