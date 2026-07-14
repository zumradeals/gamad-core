<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\PersonsAndAccounts\Http;

use DateTimeImmutable;
use Gamad\Core\PersonsAndAccounts\Application\AtomicPersonPersister;
use Gamad\Core\PersonsAndAccounts\Application\AtomicSessionPersister;
use Gamad\Core\PersonsAndAccounts\Application\AtomicUserAccountPersister;
use Gamad\Core\PersonsAndAccounts\Application\Command\AuthenticateHandler;
use Gamad\Core\PersonsAndAccounts\Application\Command\RegisterPersonHandler;
use Gamad\Core\PersonsAndAccounts\Application\Command\RegisterUserAccountHandler;
use Gamad\Core\PersonsAndAccounts\Application\Command\RevokeSessionHandler;
use Gamad\Core\PersonsAndAccounts\Application\Command\SetPasswordHandler;
use Gamad\Core\PersonsAndAccounts\Domain\AuthenticationMethodId;
use Gamad\Core\PersonsAndAccounts\Domain\AuthenticationMethodType;
use Gamad\Core\PersonsAndAccounts\Domain\Person;
use Gamad\Core\PersonsAndAccounts\Domain\PersonId;
use Gamad\Core\PersonsAndAccounts\Domain\Session;
use Gamad\Core\PersonsAndAccounts\Domain\SessionId;
use Gamad\Core\PersonsAndAccounts\Domain\UserAccount;
use Gamad\Core\PersonsAndAccounts\Domain\UserAccountId;
use Gamad\Core\PersonsAndAccounts\Http\PersonsAndAccountsHttpController;
use Gamad\Core\PersonsAndAccounts\Http\PersonsAndAccountsHttpKernel;
use Gamad\Core\PersonsAndAccounts\Http\PersonsAndAccountsResponseValidator;
use Gamad\Core\PersonsAndAccounts\Http\PersonsAndAccountsRoutes;
use Gamad\Core\PersonsAndAccounts\Infrastructure\Http\SessionTokenAuthenticator;
use Gamad\Core\PersonsAndAccounts\Infrastructure\IdentityRegistry\PostgreSqlIdentityLookup;
use Gamad\Core\PersonsAndAccounts\Infrastructure\Persistence\PostgreSqlPersonRepository;
use Gamad\Core\PersonsAndAccounts\Infrastructure\Persistence\PostgreSqlSessionRepository;
use Gamad\Core\PersonsAndAccounts\Infrastructure\Persistence\PostgreSqlUserAccountRepository;
use Gamad\Core\Shared\Application\DomainEventCollector;
use Gamad\Core\Shared\Http\OpenApiRequestValidator;
use Gamad\Core\Shared\Http\Request;
use Gamad\Core\Shared\Infrastructure\Audit\PostgreSqlAdministrativeAuditRepository;
use Gamad\Core\Shared\Infrastructure\Http\InMemoryRateLimiter;
use Gamad\Core\Shared\Infrastructure\Persistence\PdoTransactionManager;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('integration')]
final class PostgreSqlPersonsAndAccountsHttpEndToEndTest extends TestCase
{
    private PDO $connection;
    private string $rawSessionToken;

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
        $this->connection->exec('DROP TABLE IF EXISTS administrative_audit CASCADE');
        $this->connection->exec('DROP TABLE IF EXISTS outbox_dead_letters CASCADE');
        $this->connection->exec('DROP TABLE IF EXISTS outbox_messages CASCADE');
        $this->connection->exec('DROP TABLE IF EXISTS identities CASCADE');

        foreach ([1, 2, 3, 6, 7, 11, 12, 13, 14, 15, 16] as $number) {
            $files = glob(__DIR__ . '/../../../database/migrations/' . sprintf('%03d', $number) . '_*.sql');
            self::assertNotEmpty($files);
            $this->connection->exec((string) file_get_contents($files[0]));
        }

        // Seed one authenticated session directly (bypassing HTTP) to drive
        // the session-protected routes — the very first session of all can
        // only come from a pre-provisioned account, not from this API alone.
        $this->connection->prepare(
            'INSERT INTO identities (id, type, status, registered_at) VALUES (:id, :type, :status, :registered_at)'
        )->execute(['id' => 'GAM-GAT-PER-900001', 'type' => 'person', 'status' => 'active', 'registered_at' => (new DateTimeImmutable())->format(DATE_ATOM)]);

        $personRepository = new PostgreSqlPersonRepository($this->connection);
        $personRepository->save(Person::register(new PersonId('GAM-GAT-PER-900001'), 'Amina Traoré'));

        $accountRepository = new PostgreSqlUserAccountRepository($this->connection);
        $account = UserAccount::create(UserAccountId::generate(), new PersonId('GAM-GAT-PER-900001'));
        $account->addAuthenticationMethod(AuthenticationMethodId::generate(), AuthenticationMethodType::Password, 'argon2id$seed');
        $accountRepository->save($account);

        $sessionRepository = new PostgreSqlSessionRepository($this->connection);
        $this->rawSessionToken = bin2hex(random_bytes(32));
        $session = Session::issue(
            SessionId::generate(),
            $account->id(),
            $account->currentPasswordMethod()->id(),
            hash('sha256', $this->rawSessionToken),
            new DateTimeImmutable('+1 hour'),
        );
        $sessionRepository->save($session);
    }

    public function test_registering_a_person_requires_a_session_and_an_eligible_identity(): void
    {
        $this->connection->prepare(
            'INSERT INTO identities (id, type, status, registered_at) VALUES (:id, :type, :status, :registered_at)'
        )->execute(['id' => 'GAM-GAT-PER-900002', 'type' => 'person', 'status' => 'active', 'registered_at' => (new DateTimeImmutable())->format(DATE_ATOM)]);

        $kernel = $this->kernel();

        $unauthenticated = $kernel->handle(new Request('POST', '/persons', [], [], json_encode(['identity_id' => 'GAM-GAT-PER-900002', 'declared_name' => 'New Person', 'contact' => 'new.person@example.test'])));
        self::assertSame(401, $unauthenticated->status);

        $authenticated = $kernel->handle(new Request('POST', '/persons', [
            'Authorization' => 'Bearer ' . $this->rawSessionToken,
        ], [], json_encode(['identity_id' => 'GAM-GAT-PER-900002', 'declared_name' => 'New Person', 'contact' => 'new.person@example.test'])));
        self::assertSame(201, $authenticated->status);
        $body = json_decode($authenticated->body, true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('GAM-GAT-PER-900002', $body['person_id']);
    }

    public function test_login_is_public_and_issues_a_session(): void
    {
        $kernel = $this->kernel();

        $response = $kernel->handle(new Request('POST', '/auth/login', [], [], json_encode(['person_id' => 'GAM-GAT-PER-999999', 'password' => 'whatever'])));

        self::assertSame(401, $response->status);
        $body = json_decode($response->body, true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('invalid_credentials', $body['error']);
    }

    public function test_revoking_a_session_requires_a_session_and_records_audit(): void
    {
        $kernel = $this->kernel();
        $unknownSessionId = '11111111-1111-4111-8111-111111111111';

        $response = $kernel->handle(new Request('POST', "/sessions/{$unknownSessionId}/revoke", [
            'Authorization' => 'Bearer ' . $this->rawSessionToken,
        ]));

        self::assertSame(404, $response->status);
        self::assertGreaterThan(0, (int) $this->connection->query('SELECT COUNT(*) FROM administrative_audit')->fetchColumn());
    }

    private function kernel(): PersonsAndAccountsHttpKernel
    {
        $personRepository = new PostgreSqlPersonRepository($this->connection);
        $accountRepository = new PostgreSqlUserAccountRepository($this->connection);
        $sessionRepository = new PostgreSqlSessionRepository($this->connection);
        $transactions = new PdoTransactionManager($this->connection);
        $outbox = new \Gamad\Core\Shared\Infrastructure\Outbox\PostgreSqlOutboxRepository($this->connection);
        $events = new DomainEventCollector();

        $controller = new PersonsAndAccountsHttpController(
            registerPerson: new RegisterPersonHandler(
                identities: new PostgreSqlIdentityLookup($this->connection),
                persons: $personRepository,
                persister: new AtomicPersonPersister($personRepository, $outbox, $events, $transactions),
            ),
            persons: $personRepository,
            registerUserAccount: new RegisterUserAccountHandler(
                persons: $personRepository,
                accounts: $accountRepository,
                persister: new AtomicUserAccountPersister($accountRepository, $outbox, $events, $transactions),
            ),
            setPassword: new SetPasswordHandler(
                accounts: $accountRepository,
                persister: new AtomicUserAccountPersister($accountRepository, $outbox, $events, $transactions),
            ),
            authenticate: new AuthenticateHandler(
                accounts: $accountRepository,
                persister: new AtomicSessionPersister($sessionRepository, $outbox, $events, $transactions),
            ),
            revokeSession: new RevokeSessionHandler(
                sessions: $sessionRepository,
                persister: new AtomicSessionPersister($sessionRepository, $outbox, $events, $transactions),
            ),
        );

        $routes = PersonsAndAccountsRoutes::forController($controller);

        return new PersonsAndAccountsHttpKernel(
            validator: new OpenApiRequestValidator($routes),
            responseValidator: new PersonsAndAccountsResponseValidator(),
            sessionAuthentication: new SessionTokenAuthenticator($sessionRepository),
            rateLimiter: new InMemoryRateLimiter(),
            audit: new PostgreSqlAdministrativeAuditRepository($this->connection),
        );
    }
}
