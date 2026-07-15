<?php

declare(strict_types=1);

namespace Gamad\Core\OrganizationsAndMemberships\Infrastructure\PersonsAndAccounts;

use Gamad\Core\OrganizationsAndMemberships\Application\PersonLookup;
use PDO;

/**
 * Reads the `persons` table directly by plain SQL — deliberately not
 * through Gamad\Core\PersonsAndAccounts\* classes. GENESIS-012 §C only
 * grants this context a read on Persons and User Accounts, and this keeps
 * that read from ever needing to depend on that context's own vocabulary.
 */
final readonly class PostgreSqlPersonLookup implements PersonLookup
{
    public function __construct(private PDO $connection)
    {
    }

    public function exists(string $personId): bool
    {
        $statement = $this->connection->prepare('SELECT EXISTS(SELECT 1 FROM persons WHERE identity_id = :identity_id)');
        $statement->execute(['identity_id' => $personId]);

        return (bool) $statement->fetchColumn();
    }
}
