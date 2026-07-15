<?php

declare(strict_types=1);

namespace Gamad\Core\OrganizationsAndMemberships\Infrastructure\Persistence;

use DateTimeImmutable;
use Gamad\Core\OrganizationsAndMemberships\Domain\DepartmentId;
use Gamad\Core\OrganizationsAndMemberships\Domain\Membership;
use Gamad\Core\OrganizationsAndMemberships\Domain\MembershipId;
use Gamad\Core\OrganizationsAndMemberships\Domain\MembershipRepository;
use Gamad\Core\OrganizationsAndMemberships\Domain\MembershipStatus;
use Gamad\Core\OrganizationsAndMemberships\Domain\MembershipType;
use Gamad\Core\OrganizationsAndMemberships\Domain\OrganizationId;
use PDO;

final readonly class PostgreSqlMembershipRepository implements MembershipRepository
{
    public function __construct(private PDO $connection)
    {
    }

    public function save(Membership $membership): void
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
            INSERT INTO memberships (id, person_id, organization_id, department_id, membership_type, status, started_at, ended_at)
            VALUES (:id, :person_id, :organization_id, :department_id, :membership_type, :status, :started_at, :ended_at)
            ON CONFLICT (id) DO UPDATE SET
                status = EXCLUDED.status,
                ended_at = EXCLUDED.ended_at
            SQL
        );
        $statement->execute([
            'id' => (string) $membership->id(),
            'person_id' => $membership->personId(),
            'organization_id' => (string) $membership->organizationId(),
            'department_id' => $membership->departmentId() !== null ? (string) $membership->departmentId() : null,
            'membership_type' => $membership->membershipType()->value,
            'status' => $membership->status()->value,
            'started_at' => $membership->startedAt()->format(DATE_ATOM),
            'ended_at' => $membership->endedAt()?->format(DATE_ATOM),
        ]);
    }

    public function findById(MembershipId $membershipId): ?Membership
    {
        $statement = $this->connection->prepare($this->selectSql() . ' WHERE id = :id');
        $statement->execute(['id' => (string) $membershipId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->map($row);
    }

    public function findActiveByPersonAndOrganization(string $personId, OrganizationId $organizationId): ?Membership
    {
        $statement = $this->connection->prepare(
            $this->selectSql() . " WHERE person_id = :person_id AND organization_id = :organization_id AND status = 'active'"
        );
        $statement->execute(['person_id' => $personId, 'organization_id' => (string) $organizationId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->map($row);
    }

    public function findActiveByOrganization(OrganizationId $organizationId): array
    {
        $statement = $this->connection->prepare(
            $this->selectSql() . " WHERE organization_id = :organization_id AND status = 'active' ORDER BY id"
        );
        $statement->execute(['organization_id' => (string) $organizationId]);

        return $this->mapAll($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findActiveByPerson(string $personId): array
    {
        $statement = $this->connection->prepare(
            $this->selectSql() . " WHERE person_id = :person_id AND status = 'active' ORDER BY id"
        );
        $statement->execute(['person_id' => $personId]);

        return $this->mapAll($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    private function selectSql(): string
    {
        return 'SELECT id, person_id, organization_id, department_id, membership_type, status, started_at, ended_at FROM memberships';
    }

    /** @param list<array<string, mixed>> $rows
     *  @return list<Membership> */
    private function mapAll(array $rows): array
    {
        return array_map($this->map(...), $rows);
    }

    /** @param array<string, mixed> $row */
    private function map(array $row): Membership
    {
        return Membership::reconstitute(
            id: new MembershipId((string) $row['id']),
            personId: (string) $row['person_id'],
            organizationId: new OrganizationId((string) $row['organization_id']),
            membershipType: MembershipType::from((string) $row['membership_type']),
            status: MembershipStatus::from((string) $row['status']),
            startedAt: new DateTimeImmutable((string) $row['started_at']),
            departmentId: $row['department_id'] === null ? null : new DepartmentId((string) $row['department_id']),
            endedAt: $row['ended_at'] === null ? null : new DateTimeImmutable((string) $row['ended_at']),
        );
    }
}
