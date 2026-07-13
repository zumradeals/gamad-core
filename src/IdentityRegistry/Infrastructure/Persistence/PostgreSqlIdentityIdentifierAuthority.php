<?php

declare(strict_types=1);

namespace Gamad\Core\IdentityRegistry\Infrastructure\Persistence;

use Gamad\Core\IdentityRegistry\Application\AllocatedIdentityIdentifier;
use Gamad\Core\IdentityRegistry\Application\IdentityIdentifierAuthority;
use Gamad\Core\IdentityRegistry\Domain\IdentityId;
use Gamad\Core\IdentityRegistry\Domain\IdentityInternalId;
use Gamad\Core\IdentityRegistry\Domain\IdentityType;
use PDO;
use RuntimeException;

final readonly class PostgreSqlIdentityIdentifierAuthority implements IdentityIdentifierAuthority
{
    public function __construct(private PDO $connection) {}

    public function allocate(IdentityType $type): AllocatedIdentityIdentifier
    {
        $statement = $this->connection->prepare(
            <<<'SQL'
            UPDATE identity_identifier_sequences
            SET last_value = last_value + 1,
                updated_at = NOW()
            WHERE identity_type = :identity_type
            RETURNING prefix, last_value
            SQL
        );
        $statement->execute(['identity_type' => $type->value]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            throw new RuntimeException(sprintf('No identifier sequence is configured for identity type %s.', $type->value));
        }

        return new AllocatedIdentityIdentifier(
            internalId: IdentityInternalId::generate(),
            publicId: new IdentityId(sprintf('GAM-%s-%06d', (string) $row['prefix'], (int) $row['last_value'])),
        );
    }
}
