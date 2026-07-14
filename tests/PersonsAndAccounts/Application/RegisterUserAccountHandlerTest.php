<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\PersonsAndAccounts\Application;

use Gamad\Core\PersonsAndAccounts\Application\AtomicPersonPersister;
use Gamad\Core\PersonsAndAccounts\Application\AtomicUserAccountPersister;
use Gamad\Core\PersonsAndAccounts\Application\Command\RegisterPerson;
use Gamad\Core\PersonsAndAccounts\Application\Command\RegisterPersonHandler;
use Gamad\Core\PersonsAndAccounts\Application\Command\RegisterUserAccount;
use Gamad\Core\PersonsAndAccounts\Application\Command\RegisterUserAccountHandler;
use Gamad\Core\PersonsAndAccounts\Application\Exception\PersonNotFound;
use Gamad\Core\PersonsAndAccounts\Application\Exception\UserAccountAlreadyExists;
use Gamad\Core\PersonsAndAccounts\Domain\UserAccountStatus;
use Gamad\Core\PersonsAndAccounts\Infrastructure\Persistence\InMemoryIdentityLookup;
use Gamad\Core\PersonsAndAccounts\Infrastructure\Persistence\InMemoryPersonRepository;
use Gamad\Core\PersonsAndAccounts\Infrastructure\Persistence\InMemoryUserAccountRepository;
use Gamad\Core\Shared\Application\DomainEventCollector;
use Gamad\Core\Tests\Support\InMemoryOutboxRepository;
use Gamad\Core\Tests\Support\SynchronousTransactionManager;
use PHPUnit\Framework\TestCase;

final class RegisterUserAccountHandlerTest extends TestCase
{
    public function test_it_creates_an_active_account_for_an_existing_person(): void
    {
        [$handler] = $this->handlerWithRegisteredPerson('GAM-PER-000001');

        $account = $handler(new RegisterUserAccount('GAM-PER-000001'));

        self::assertSame(UserAccountStatus::Active, $account->status());
    }

    public function test_it_rejects_a_second_account_for_the_same_person(): void
    {
        [$handler] = $this->handlerWithRegisteredPerson('GAM-PER-000001');
        $handler(new RegisterUserAccount('GAM-PER-000001'));

        $this->expectException(UserAccountAlreadyExists::class);

        $handler(new RegisterUserAccount('GAM-PER-000001'));
    }

    public function test_it_rejects_an_account_for_a_person_that_does_not_exist(): void
    {
        $persons = new InMemoryPersonRepository();
        $accounts = new InMemoryUserAccountRepository();
        $outbox = new InMemoryOutboxRepository();
        $handler = new RegisterUserAccountHandler(
            persons: $persons,
            accounts: $accounts,
            persister: new AtomicUserAccountPersister($accounts, $outbox, new DomainEventCollector(), new SynchronousTransactionManager()),
        );

        $this->expectException(PersonNotFound::class);

        $handler(new RegisterUserAccount('GAM-PER-999999'));
    }

    /** @return array{0: RegisterUserAccountHandler, 1: InMemoryUserAccountRepository} */
    private function handlerWithRegisteredPerson(string $identityId): array
    {
        $identities = new InMemoryIdentityLookup();
        $identities->register($identityId, 'person', 'active');
        $persons = new InMemoryPersonRepository();
        $personOutbox = new InMemoryOutboxRepository();
        (new RegisterPersonHandler(
            identities: $identities,
            persons: $persons,
            persister: new AtomicPersonPersister($persons, $personOutbox, new DomainEventCollector(), new SynchronousTransactionManager()),
        ))(new RegisterPerson($identityId, 'Amina Traoré'));

        $accounts = new InMemoryUserAccountRepository();
        $outbox = new InMemoryOutboxRepository();
        $handler = new RegisterUserAccountHandler(
            persons: $persons,
            accounts: $accounts,
            persister: new AtomicUserAccountPersister($accounts, $outbox, new DomainEventCollector(), new SynchronousTransactionManager()),
        );

        return [$handler, $accounts];
    }
}
