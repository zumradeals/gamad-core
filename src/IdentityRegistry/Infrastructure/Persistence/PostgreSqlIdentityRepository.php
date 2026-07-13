<?php

declare(strict_types=1);

namespace Gamad\Core\IdentityRegistry\Infrastructure\Persistence;

use DateTimeImmutable;
use Gamad\Core\IdentityRegistry\Application\IdentitySearchPage;
use Gamad\Core\IdentityRegistry\Application\IdentitySearchRepository;
use Gamad\Core\IdentityRegistry\Domain\Identity;
use Gamad\Core\IdentityRegistry\Domain\IdentityId;
use Gamad\Core\IdentityRegistry\Domain\IdentityInternalId;
use Gamad\Core\IdentityRegistry\Domain\IdentityRepository;
use Gamad\Core\IdentityRegistry\Domain\IdentityStatus;
use Gamad\Core\IdentityRegistry\Domain\IdentityType;
use PDO;

final readonly class PostgreSqlIdentityRepository implements IdentityRepository, IdentitySearchRepository
{
    public function __construct(private PDO $connection) {}

    public function save(Identity $identity): void
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
            INSERT INTO identities (internal_id, id, type, status, registered_at)
            VALUES (:internal_id, :id, :type, :status, :registered_at)
            ON CONFLICT (id) DO UPDATE SET status = EXCLUDED.status
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

        return $row === false ? null : $this->map($row);
    }

    public function exists(IdentityId $identityId): bool
    {
        $statement = $this->connection->prepare('SELECT EXISTS(SELECT 1 FROM identities WHERE id = :id)');
        $statement->execute(['id' => (string) $identityId]);
        return (bool) $statement->fetchColumn();
    }

    public function search(?IdentityType $type, ?IdentityStatus $status, int $limit, ?string $cursor): IdentitySearchPage
    {
        $conditions = [];
        $parameters = [];

        if ($type !== null) {
            $conditions[] = 'type = :type';
            $parameters['type'] = $type->value;
        }
        if ($status !== null) {
            $conditions[] = 'status = :status';
            $parameters['status'] = $status->value;
        }
        if ($cursor !== null) {
            $conditions[] = 'id > :cursor';
            $parameters['cursor'] = $cursor;
        }

        $where = $conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions);
        $sql = sprintf(
            'SELECT internal_id, id, type, status, registered_at FROM identities %s ORDER BY id ASC LIMIT :limit',
            $where,
        );
        $statement = $this->connection->prepare($sql);
        foreach ($parameters as $name => $value) {
            $statement->bindValue($name, $value);
        }
        $statement->bindValue('limit', $limit + 1, PDO::PARAM_INT);
        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            array_pop($rows);
        }
        $items = array_map(fn (array $row): Identity => $this->map($row), $rows);

        return new IdentitySearchPage(
            items: $items,
            nextCursor: $hasMore && $items !== [] ? (string) end($items)->id() : null,
        );
    }

    /** @param array<string, mixed> $row */
    private function map(array $row): Identity
    {
        return Identity::reconstitute(
            internalId: new IdentityInternalId((string) $row['internal_id']),
            id: new IdentityId((string) $row['id']),
            type: IdentityType::from((string) $row['type']),
            status: IdentityStatus::from((string) $row['status']),
            registeredAt: new DateTimeImmutable((string) $row['registered_at']),
        );
    }
}
