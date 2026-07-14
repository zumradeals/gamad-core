<?php

declare(strict_types=1);

namespace Gamad\Core\PersonsAndAccounts\Infrastructure\Persistence;

use DateTimeImmutable;
use Gamad\Core\PersonsAndAccounts\Domain\Person;
use Gamad\Core\PersonsAndAccounts\Domain\PersonId;
use Gamad\Core\PersonsAndAccounts\Domain\PersonRepository;
use Gamad\Core\PersonsAndAccounts\Domain\PersonStatus;
use PDO;

final readonly class PostgreSqlPersonRepository implements PersonRepository
{
    public function __construct(private PDO $connection)
    {
    }

    public function save(Person $person): void
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
            INSERT INTO persons (identity_id, declared_name, status, registered_at)
            VALUES (:identity_id, :declared_name, :status, :registered_at)
            ON CONFLICT (identity_id) DO UPDATE SET
                declared_name = EXCLUDED.declared_name,
                status = EXCLUDED.status
            SQL
        );
        $statement->execute([
            'identity_id' => (string) $person->id(),
            'declared_name' => $person->declaredName(),
            'status' => $person->status()->value,
            'registered_at' => $person->registeredAt()->format(DATE_ATOM),
        ]);
    }

    public function findById(PersonId $personId): ?Person
    {
        $statement = $this->connection->prepare(
            'SELECT identity_id, declared_name, status, registered_at FROM persons WHERE identity_id = :identity_id'
        );
        $statement->execute(['identity_id' => (string) $personId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->map($row);
    }

    public function exists(PersonId $personId): bool
    {
        $statement = $this->connection->prepare('SELECT EXISTS(SELECT 1 FROM persons WHERE identity_id = :identity_id)');
        $statement->execute(['identity_id' => (string) $personId]);

        return (bool) $statement->fetchColumn();
    }

    /** @param array<string, mixed> $row */
    private function map(array $row): Person
    {
        return Person::reconstitute(
            id: new PersonId((string) $row['identity_id']),
            declaredName: (string) $row['declared_name'],
            status: PersonStatus::from((string) $row['status']),
            registeredAt: new DateTimeImmutable((string) $row['registered_at']),
        );
    }
}
