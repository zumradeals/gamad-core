<?php

declare(strict_types=1);

namespace Gamad\Core\AccessControl\Infrastructure\Persistence;

use Gamad\Core\AccessControl\Application\AccessControlLookup;
use PDO;

/**
 * Reads the `persons` and `organizations` tables directly by plain SQL —
 * deliberately not through PersonsAndAccounts\* or
 * OrganizationsAndMemberships\* classes (GENESIS-014 §C, ADR-0013), same
 * patron as the existing cross-context lookups.
 */
final readonly class PostgreSqlAccessControlLookup implements AccessControlLookup
{
    public function __construct(private PDO $connection)
    {
    }

    public function personExists(string $personId): bool
    {
        $statement = $this->connection->prepare('SELECT EXISTS(SELECT 1 FROM persons WHERE identity_id = :identity_id)');
        $statement->execute(['identity_id' => $personId]);

        return (bool) $statement->fetchColumn();
    }

    public function organizationExists(string $organizationId): bool
    {
        $statement = $this->connection->prepare('SELECT EXISTS(SELECT 1 FROM organizations WHERE identity_id = :identity_id)');
        $statement->execute(['identity_id' => $organizationId]);

        return (bool) $statement->fetchColumn();
    }

    public function resolveAccountToPerson(string $accountId): ?string
    {
        $statement = $this->connection->prepare('SELECT person_id FROM user_accounts WHERE id = :id');
        $statement->execute(['id' => $accountId]);
        $personId = $statement->fetchColumn();

        return $personId === false ? null : (string) $personId;
    }
}
