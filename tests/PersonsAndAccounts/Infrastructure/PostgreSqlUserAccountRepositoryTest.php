<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\PersonsAndAccounts\Infrastructure;

use DateTimeImmutable;
use Gamad\Core\PersonsAndAccounts\Domain\AuthenticationMethodId;
use Gamad\Core\PersonsAndAccounts\Domain\AuthenticationMethodType;
use Gamad\Core\PersonsAndAccounts\Domain\Person;
use Gamad\Core\PersonsAndAccounts\Domain\PersonId;
use Gamad\Core\PersonsAndAccounts\Domain\UserAccount;
use Gamad\Core\PersonsAndAccounts\Domain\UserAccountId;
use Gamad\Core\PersonsAndAccounts\Domain\UserAccountStatus;
use Gamad\Core\PersonsAndAccounts\Infrastructure\Persistence\PostgreSqlPersonRepository;
use Gamad\Core\PersonsAndAccounts\Infrastructure\Persistence\PostgreSqlUserAccountRepository;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('integration')]
final class PostgreSqlUserAccountRepositoryTest extends TestCase
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

        foreach ([1, 11, 12, 13, 14, 15, 16] as $number) {
            $files = glob(__DIR__ . '/../../../database/migrations/' . sprintf('%03d', $number) . '_*.sql');
            self::assertNotEmpty($files);
            $this->connection->exec((string) file_get_contents($files[0]));
        }

        $this->connection->prepare(
            'INSERT INTO identities (id, type, status, registered_at) VALUES (:id, :type, :status, :registered_at)'
        )->execute(['id' => 'GAM-GAT-PER-900001', 'type' => 'person', 'status' => 'active', 'registered_at' => (new DateTimeImmutable())->format(DATE_ATOM)]);
        (new PostgreSqlPersonRepository($this->connection))->save(Person::register(new PersonId('GAM-GAT-PER-900001'), 'Amina Traoré'));
    }

    public function test_it_saves_and_finds_an_account_with_its_authentication_methods(): void
    {
        $repository = new PostgreSqlUserAccountRepository($this->connection);
        $account = UserAccount::create(UserAccountId::generate(), new PersonId('GAM-GAT-PER-900001'));
        $account->addAuthenticationMethod(AuthenticationMethodId::generate(), AuthenticationMethodType::Password, 'argon2id$hash-one', new DateTimeImmutable('2026-01-01T00:00:00+00:00'));
        $repository->save($account);

        $account->addAuthenticationMethod(AuthenticationMethodId::generate(), AuthenticationMethodType::Password, 'argon2id$hash-two', new DateTimeImmutable('2026-07-14T00:00:00+00:00'));
        $repository->save($account);

        $found = $repository->findByPersonId(new PersonId('GAM-GAT-PER-900001'));

        self::assertNotNull($found);
        self::assertSame(UserAccountStatus::Active, $found->status());
        self::assertCount(2, $found->authenticationMethods());
        self::assertSame('argon2id$hash-two', $found->currentPasswordMethod()?->credentialRef());
        self::assertTrue($repository->existsForPerson(new PersonId('GAM-GAT-PER-900001')));
    }

    public function test_it_enforces_at_most_one_account_per_person_at_the_database_level(): void
    {
        $repository = new PostgreSqlUserAccountRepository($this->connection);
        $repository->save(UserAccount::create(UserAccountId::generate(), new PersonId('GAM-GAT-PER-900001')));

        $this->expectException(\PDOException::class);

        $repository->save(UserAccount::create(UserAccountId::generate(), new PersonId('GAM-GAT-PER-900001')));
    }

    public function test_it_persists_status_transitions(): void
    {
        $repository = new PostgreSqlUserAccountRepository($this->connection);
        $account = UserAccount::create(UserAccountId::generate(), new PersonId('GAM-GAT-PER-900001'));
        $repository->save($account);

        $account->suspend();
        $repository->save($account);

        self::assertSame(UserAccountStatus::Suspended, $repository->findById($account->id())?->status());
    }
}
