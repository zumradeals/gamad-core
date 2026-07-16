<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\AccessControl\Infrastructure;

use Gamad\Core\AccessControl\Domain\Permission;
use Gamad\Core\AccessControl\Domain\PermissionId;
use Gamad\Core\AccessControl\Infrastructure\Persistence\PostgreSqlPermissionRepository;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('integration')]
final class PostgreSqlPermissionRepositoryTest extends TestCase
{
    private PDO $connection;

    protected function setUp(): void
    {
        $dsn = getenv('GAMAD_TEST_PG_DSN');
        if ($dsn === false || $dsn === '') {
            self::markTestSkipped('Set GAMAD_TEST_PG_DSN to run PostgreSQL integration tests.');
        }

        $this->connection = new PDO(
            $dsn,
            getenv('GAMAD_TEST_PG_USER') ?: null,
            getenv('GAMAD_TEST_PG_PASSWORD') ?: null,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );

        $this->connection->exec('DROP TABLE IF EXISTS role_assignments CASCADE');
        $this->connection->exec('DROP TABLE IF EXISTS role_permissions CASCADE');
        $this->connection->exec('DROP TABLE IF EXISTS roles CASCADE');
        $this->connection->exec('DROP TABLE IF EXISTS permissions CASCADE');

        foreach ([22, 23, 24, 25] as $number) {
            $files = glob(__DIR__ . '/../../../database/migrations/' . sprintf('%03d', $number) . '_*.sql');
            self::assertNotEmpty($files);
            $this->connection->exec((string) file_get_contents($files[0]));
        }
    }

    public function test_it_saves_and_finds_a_permission(): void
    {
        $repository = new PostgreSqlPermissionRepository($this->connection);
        $permission = new Permission(PermissionId::generate(), 'identity:read', 'Read an identity');

        $repository->save($permission);

        $found = $repository->findById($permission->id);
        self::assertNotNull($found);
        self::assertSame('identity:read', $found->name);

        $byName = $repository->findByName('identity:read');
        self::assertNotNull($byName);
        self::assertTrue($byName->id->equals($permission->id));
    }

    public function test_it_returns_null_for_an_unknown_permission(): void
    {
        $repository = new PostgreSqlPermissionRepository($this->connection);

        self::assertNull($repository->findById(PermissionId::generate()));
        self::assertNull($repository->findByName('does:not:exist'));
    }
}
