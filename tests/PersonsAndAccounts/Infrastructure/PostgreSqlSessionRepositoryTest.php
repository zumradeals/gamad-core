<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\PersonsAndAccounts\Infrastructure;

use DateTimeImmutable;
use Gamad\Core\PersonsAndAccounts\Domain\AuthenticationMethodId;
use Gamad\Core\PersonsAndAccounts\Domain\AuthenticationMethodType;
use Gamad\Core\PersonsAndAccounts\Domain\Person;
use Gamad\Core\PersonsAndAccounts\Domain\PersonId;
use Gamad\Core\PersonsAndAccounts\Domain\Session;
use Gamad\Core\PersonsAndAccounts\Domain\SessionId;
use Gamad\Core\PersonsAndAccounts\Domain\UserAccount;
use Gamad\Core\PersonsAndAccounts\Domain\UserAccountId;
use Gamad\Core\PersonsAndAccounts\Infrastructure\Persistence\PostgreSqlPersonRepository;
use Gamad\Core\PersonsAndAccounts\Infrastructure\Persistence\PostgreSqlSessionRepository;
use Gamad\Core\PersonsAndAccounts\Infrastructure\Persistence\PostgreSqlUserAccountRepository;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('integration')]
final class PostgreSqlSessionRepositoryTest extends TestCase
{
    private PDO $connection;
    private UserAccountId $accountId;
    private AuthenticationMethodId $methodId;

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

        $account = UserAccount::create(UserAccountId::generate(), new PersonId('GAM-GAT-PER-900001'));
        $this->methodId = AuthenticationMethodId::generate();
        $account->addAuthenticationMethod($this->methodId, AuthenticationMethodType::Password, 'argon2id$hash');
        (new PostgreSqlUserAccountRepository($this->connection))->save($account);
        $this->accountId = $account->id();
    }

    public function test_it_saves_and_finds_a_session_by_id_and_token_hash(): void
    {
        $repository = new PostgreSqlSessionRepository($this->connection);
        $tokenHash = hash('sha256', 'raw-token');
        $session = Session::issue($this->sessionId(), $this->accountId, $this->methodId, $tokenHash, new DateTimeImmutable('+1 hour'));

        $repository->save($session);

        self::assertNotNull($repository->findById($session->id()));
        self::assertNotNull($repository->findByTokenHash($tokenHash));
        self::assertNull($repository->findByTokenHash(hash('sha256', 'other-token')));
    }

    public function test_it_finds_only_active_sessions_for_an_account(): void
    {
        $repository = new PostgreSqlSessionRepository($this->connection);

        $active = Session::issue($this->sessionId(), $this->accountId, $this->methodId, hash('sha256', 'a'), new DateTimeImmutable('+1 hour'));
        $repository->save($active);

        $expired = Session::issue($this->sessionId(), $this->accountId, $this->methodId, hash('sha256', 'b'), new DateTimeImmutable('-1 hour'));
        $repository->save($expired);

        $revoked = Session::issue($this->sessionId(), $this->accountId, $this->methodId, hash('sha256', 'c'), new DateTimeImmutable('+1 hour'));
        $revoked->revoke('manual_revoke');
        $repository->save($revoked);

        $activeSessions = $repository->findActiveByUserAccountId($this->accountId);

        self::assertCount(1, $activeSessions);
        self::assertTrue($activeSessions[0]->id()->equals($active->id()));
    }

    public function test_it_persists_revocation(): void
    {
        $repository = new PostgreSqlSessionRepository($this->connection);
        $session = Session::issue($this->sessionId(), $this->accountId, $this->methodId, hash('sha256', 'x'), new DateTimeImmutable('+1 hour'));
        $repository->save($session);

        $session->revoke('manual_revoke');
        $repository->save($session);

        self::assertTrue($repository->findById($session->id())?->isRevoked());
    }

    private function sessionId(): SessionId
    {
        return SessionId::generate();
    }
}
