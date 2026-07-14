<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\PersonsAndAccounts\Infrastructure;

use DateTimeImmutable;
use Gamad\Core\PersonsAndAccounts\Domain\Person;
use Gamad\Core\PersonsAndAccounts\Domain\PersonId;
use Gamad\Core\PersonsAndAccounts\Domain\PersonStatus;
use Gamad\Core\PersonsAndAccounts\Infrastructure\IdentityRegistry\PostgreSqlIdentityLookup;
use Gamad\Core\PersonsAndAccounts\Infrastructure\Persistence\PostgreSqlPersonRepository;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('integration')]
final class PostgreSqlPersonRepositoryTest extends TestCase
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

        $this->connection->exec('DROP TABLE IF EXISTS sessions CASCADE');
        $this->connection->exec('DROP TABLE IF EXISTS authentication_methods CASCADE');
        $this->connection->exec('DROP TABLE IF EXISTS user_accounts CASCADE');
        $this->connection->exec('DROP TABLE IF EXISTS persons CASCADE');
        $this->connection->exec('DROP TABLE IF EXISTS identities CASCADE');

        foreach ([1, 11, 12, 13, 14] as $number) {
            $files = glob(__DIR__ . '/../../../database/migrations/' . sprintf('%03d', $number) . '_*.sql');
            self::assertNotEmpty($files);
            $this->connection->exec((string) file_get_contents($files[0]));
        }
    }

    public function test_it_saves_and_finds_a_person_by_id(): void
    {
        $this->insertIdentity('GAM-PER-900001');
        $repository = new PostgreSqlPersonRepository($this->connection);
        $person = Person::register(new PersonId('GAM-PER-900001'), 'Amina Traoré', new DateTimeImmutable('2026-07-14T00:00:00+00:00'));

        $repository->save($person);
        $found = $repository->findById(new PersonId('GAM-PER-900001'));

        self::assertNotNull($found);
        self::assertSame('Amina Traoré', $found->declaredName());
        self::assertSame(PersonStatus::Active, $found->status());
        self::assertTrue($repository->exists(new PersonId('GAM-PER-900001')));
    }

    public function test_it_rejects_a_person_referencing_a_nonexistent_identity(): void
    {
        $repository = new PostgreSqlPersonRepository($this->connection);
        $person = Person::register(new PersonId('GAM-PER-900099'), 'Ghost');

        $this->expectException(\PDOException::class);

        $repository->save($person);
    }

    public function test_identity_lookup_reads_the_identity_registry_table_directly(): void
    {
        $this->insertIdentity('GAM-PER-900002');
        $lookup = new PostgreSqlIdentityLookup($this->connection);

        $result = $lookup->find('GAM-PER-900002');

        self::assertNotNull($result);
        self::assertSame('person', $result->type);
        self::assertSame('active', $result->status);
        self::assertNull($lookup->find('GAM-PER-999999'));
    }

    private function insertIdentity(string $id): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO identities (id, type, status, registered_at) VALUES (:id, :type, :status, :registered_at)'
        );
        $statement->execute([
            'id' => $id,
            'type' => 'person',
            'status' => 'active',
            'registered_at' => (new DateTimeImmutable())->format(DATE_ATOM),
        ]);
    }
}
