<?php

declare(strict_types=1);

namespace Gamad\Core\OrganizationsAndMemberships\Infrastructure\IdentityRegistry;

use Gamad\Core\OrganizationsAndMemberships\Application\IdentityLookup;
use Gamad\Core\OrganizationsAndMemberships\Application\IdentityLookupResult;
use PDO;

/**
 * Reads the `identities` table directly by plain SQL — deliberately not
 * through Gamad\Core\IdentityRegistry\* classes. GENESIS-012 §C only grants
 * this context a read on the Identity Registry, and this keeps that read
 * from ever needing to depend on that context's Domain/Application types.
 */
final readonly class PostgreSqlIdentityLookup implements IdentityLookup
{
    public function __construct(private PDO $connection)
    {
    }

    public function find(string $identityId): ?IdentityLookupResult
    {
        $statement = $this->connection->prepare('SELECT id, type, status FROM identities WHERE id = :id');
        $statement->execute(['id' => $identityId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return new IdentityLookupResult((string) $row['id'], (string) $row['type'], (string) $row['status']);
    }
}
