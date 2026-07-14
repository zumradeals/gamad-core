<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\IdentityRegistry\Infrastructure;

use Gamad\Core\IdentityRegistry\Domain\IdentityType;
use Gamad\Core\IdentityRegistry\Infrastructure\Persistence\PostgreSqlIdentityIdentifierAuthority;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('integration')]
final class PostgreSqlIdentityIdentifierAuthorityConcurrencyTest extends TestCase
{
    public function test_multiple_connections_allocate_unique_monotonic_public_identifiers(): void
    {
        $dsn = getenv('GAMAD_TEST_PG_DSN');
        if ($dsn === false || $dsn === '') {
            self::markTestSkipped('Set GAMAD_TEST_PG_DSN to run PostgreSQL integration tests.');
        }

        $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
        $connections = [];
        for ($index = 0; $index < 8; ++$index) {
            $connections[] = new PDO(
                $dsn,
                getenv('GAMAD_TEST_PG_USER') ?: null,
                getenv('GAMAD_TEST_PG_PASSWORD') ?: null,
                $options,
            );
        }

        $connections[0]->exec("UPDATE identity_identifier_sequences SET last_value = 0 WHERE identity_type = 'person'");

        $allocated = [];
        for ($attempt = 0; $attempt < 200; ++$attempt) {
            $authority = new PostgreSqlIdentityIdentifierAuthority($connections[$attempt % count($connections)]);
            $allocated[] = (string) $authority->allocate(IdentityType::Person, 'GAT')->publicId;
        }

        self::assertCount(200, array_unique($allocated));
        self::assertSame('GAM-GAT-PER-000001', $allocated[0]);
        self::assertSame('GAM-GAT-PER-000200', $allocated[199]);
        self::assertSame(
            200,
            (int) $connections[0]->query("SELECT last_value FROM identity_identifier_sequences WHERE identity_type = 'person'")->fetchColumn(),
        );
    }
}
