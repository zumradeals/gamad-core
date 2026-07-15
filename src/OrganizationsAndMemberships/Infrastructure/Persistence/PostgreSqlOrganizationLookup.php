<?php

declare(strict_types=1);

namespace Gamad\Core\OrganizationsAndMemberships\Infrastructure\Persistence;

use Gamad\Core\OrganizationsAndMemberships\Domain\OrganizationId;
use PDO;

/**
 * Read-only resolution of the organization parentage tree (GENESIS-011 §2.1)
 * — direct children reuse the simple query already on
 * OrganizationRepository::findChildren(); walking *up* toward the root
 * across an arbitrary number of levels is what genuinely needs recursion,
 * so a `WITH RECURSIVE` query is used here, as authorized by DIRECTIVE-006
 * Task 5.
 */
final readonly class PostgreSqlOrganizationLookup
{
    public function __construct(private PDO $connection)
    {
    }

    /**
     * The organization itself and every ancestor up to the root, ordered
     * root-first. A root organization (no parent) returns a single row.
     *
     * @return list<string>
     */
    public function ancestryChain(OrganizationId $organizationId): array
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
            WITH RECURSIVE ancestry AS (
                SELECT identity_id, parent_id, 0 AS depth
                FROM organizations
                WHERE identity_id = :organization_id

                UNION ALL

                SELECT o.identity_id, o.parent_id, ancestry.depth + 1
                FROM organizations o
                INNER JOIN ancestry ON o.identity_id = ancestry.parent_id
            )
            SELECT identity_id FROM ancestry ORDER BY depth DESC
            SQL
        );
        $statement->execute(['organization_id' => (string) $organizationId]);

        return array_map(strval(...), $statement->fetchAll(PDO::FETCH_COLUMN));
    }
}
