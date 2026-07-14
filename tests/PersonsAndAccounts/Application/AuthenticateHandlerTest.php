<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\PersonsAndAccounts\Application;

use Gamad\Core\PersonsAndAccounts\Application\AtomicSessionPersister;
use Gamad\Core\PersonsAndAccounts\Application\Command\Authenticate;
use Gamad\Core\PersonsAndAccounts\Application\Command\AuthenticateHandler;
use Gamad\Core\PersonsAndAccounts\Application\Exception\AuthenticationFailed;
use Gamad\Core\PersonsAndAccounts\Domain\AuthenticationMethodId;
use Gamad\Core\PersonsAndAccounts\Domain\AuthenticationMethodType;
use Gamad\Core\PersonsAndAccounts\Domain\PersonId;
use Gamad\Core\PersonsAndAccounts\Domain\UserAccount;
use Gamad\Core\PersonsAndAccounts\Domain\UserAccountId;
use Gamad\Core\PersonsAndAccounts\Infrastructure\Persistence\InMemorySessionRepository;
use Gamad\Core\PersonsAndAccounts\Infrastructure\Persistence\InMemoryUserAccountRepository;
use Gamad\Core\Shared\Application\DomainEventCollector;
use Gamad\Core\Tests\Support\InMemoryOutboxRepository;
use Gamad\Core\Tests\Support\SynchronousTransactionManager;
use PHPUnit\Framework\TestCase;

final class AuthenticateHandlerTest extends TestCase
{
    public function test_it_issues_a_session_on_successful_authentication(): void
    {
        $personId = new PersonId('GAM-GAT-PER-000001');
        [$handler, , $sessions] = $this->handlerWithAccount($personId, 'correct horse battery staple');

        $result = $handler(new Authenticate((string) $personId, 'correct horse battery staple'));

        self::assertNotSame('', $result->token);
        self::assertNotNull($sessions->findByTokenHash(hash('sha256', $result->token)));
    }

    public function test_it_fails_generically_for_an_unknown_person(): void
    {
        $accounts = new InMemoryUserAccountRepository();
        $sessions = new InMemorySessionRepository();
        $outbox = new InMemoryOutboxRepository();
        $handler = new AuthenticateHandler(
            accounts: $accounts,
            persister: new AtomicSessionPersister($sessions, $outbox, new DomainEventCollector(), new SynchronousTransactionManager()),
        );

        try {
            $handler(new Authenticate('GAM-GAT-PER-999999', 'whatever'));
            self::fail('Expected AuthenticationFailed to be thrown.');
        } catch (AuthenticationFailed $exception) {
            self::assertSame('Invalid credentials.', $exception->getMessage());
        }
    }

    public function test_it_fails_with_the_same_generic_message_for_a_wrong_password(): void
    {
        $personId = new PersonId('GAM-GAT-PER-000001');
        [$handler] = $this->handlerWithAccount($personId, 'correct horse battery staple');

        try {
            $handler(new Authenticate((string) $personId, 'wrong password'));
            self::fail('Expected AuthenticationFailed to be thrown.');
        } catch (AuthenticationFailed $exception) {
            self::assertSame('Invalid credentials.', $exception->getMessage());
        }
    }

    public function test_it_rejects_authentication_for_a_suspended_account(): void
    {
        $personId = new PersonId('GAM-GAT-PER-000001');
        [$handler, $accounts] = $this->handlerWithAccount($personId, 'correct horse battery staple');
        $account = $accounts->findByPersonId($personId);
        $account?->suspend();
        $accounts->save($account);

        $this->expectException(AuthenticationFailed::class);

        $handler(new Authenticate((string) $personId, 'correct horse battery staple'));
    }

    /** @return array{0: AuthenticateHandler, 1: InMemoryUserAccountRepository, 2: InMemorySessionRepository} */
    private function handlerWithAccount(PersonId $personId, string $password): array
    {
        $accounts = new InMemoryUserAccountRepository();
        $account = UserAccount::create(UserAccountId::generate(), $personId);
        $account->addAuthenticationMethod(
            AuthenticationMethodId::generate(),
            AuthenticationMethodType::Password,
            password_hash($password, PASSWORD_ARGON2ID),
        );
        $accounts->save($account);

        $sessions = new InMemorySessionRepository();
        $outbox = new InMemoryOutboxRepository();
        $handler = new AuthenticateHandler(
            accounts: $accounts,
            persister: new AtomicSessionPersister($sessions, $outbox, new DomainEventCollector(), new SynchronousTransactionManager()),
        );

        return [$handler, $accounts, $sessions];
    }
}
