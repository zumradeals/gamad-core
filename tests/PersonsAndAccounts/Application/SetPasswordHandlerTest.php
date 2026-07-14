<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\PersonsAndAccounts\Application;

use Gamad\Core\PersonsAndAccounts\Application\AtomicUserAccountPersister;
use Gamad\Core\PersonsAndAccounts\Application\Command\SetPassword;
use Gamad\Core\PersonsAndAccounts\Application\Command\SetPasswordHandler;
use Gamad\Core\PersonsAndAccounts\Application\Exception\UserAccountNotFound;
use Gamad\Core\PersonsAndAccounts\Domain\AuthenticationMethodType;
use Gamad\Core\PersonsAndAccounts\Domain\PersonId;
use Gamad\Core\PersonsAndAccounts\Domain\UserAccount;
use Gamad\Core\PersonsAndAccounts\Domain\UserAccountId;
use Gamad\Core\PersonsAndAccounts\Infrastructure\Persistence\InMemoryUserAccountRepository;
use Gamad\Core\Shared\Application\DomainEventCollector;
use Gamad\Core\Tests\Support\InMemoryOutboxRepository;
use Gamad\Core\Tests\Support\SynchronousTransactionManager;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SetPasswordHandlerTest extends TestCase
{
    public function test_it_stores_an_argon2id_hash_never_the_plaintext(): void
    {
        $accounts = new InMemoryUserAccountRepository();
        $account = UserAccount::create(UserAccountId::generate(), new PersonId('GAM-GAT-PER-000001'));
        $accounts->save($account);
        $outbox = new InMemoryOutboxRepository();
        $handler = new SetPasswordHandler(
            accounts: $accounts,
            persister: new AtomicUserAccountPersister($accounts, $outbox, new DomainEventCollector(), new SynchronousTransactionManager()),
        );

        $handler(new SetPassword((string) $account->id(), 'correct horse battery staple'));

        $stored = $accounts->findById($account->id());
        $method = $stored?->currentPasswordMethod();

        self::assertNotNull($method);
        self::assertSame(AuthenticationMethodType::Password, $method->type());
        self::assertStringStartsWith('$argon2id$', $method->credentialRef());
        self::assertStringNotContainsString('correct horse battery staple', $method->credentialRef());
        self::assertTrue(password_verify('correct horse battery staple', $method->credentialRef()));
    }

    public function test_it_rejects_an_empty_password(): void
    {
        $accounts = new InMemoryUserAccountRepository();
        $account = UserAccount::create(UserAccountId::generate(), new PersonId('GAM-GAT-PER-000001'));
        $accounts->save($account);
        $outbox = new InMemoryOutboxRepository();
        $handler = new SetPasswordHandler(
            accounts: $accounts,
            persister: new AtomicUserAccountPersister($accounts, $outbox, new DomainEventCollector(), new SynchronousTransactionManager()),
        );

        $this->expectException(InvalidArgumentException::class);

        $handler(new SetPassword((string) $account->id(), ''));
    }

    public function test_it_rejects_an_unknown_account(): void
    {
        $accounts = new InMemoryUserAccountRepository();
        $outbox = new InMemoryOutboxRepository();
        $handler = new SetPasswordHandler(
            accounts: $accounts,
            persister: new AtomicUserAccountPersister($accounts, $outbox, new DomainEventCollector(), new SynchronousTransactionManager()),
        );

        $this->expectException(UserAccountNotFound::class);

        $handler(new SetPassword('11111111-1111-4111-8111-111111111111', 'irrelevant'));
    }
}
