<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\PersonsAndAccounts\Application;

use Gamad\Core\PersonsAndAccounts\Application\AtomicPersonPersister;
use Gamad\Core\PersonsAndAccounts\Application\Command\RegisterPerson;
use Gamad\Core\PersonsAndAccounts\Application\Command\RegisterPersonHandler;
use Gamad\Core\PersonsAndAccounts\Application\Exception\IdentityNotEligibleForPerson;
use Gamad\Core\PersonsAndAccounts\Application\Exception\PersonAlreadyExists;
use Gamad\Core\PersonsAndAccounts\Domain\PersonId;
use Gamad\Core\PersonsAndAccounts\Domain\PersonStatus;
use Gamad\Core\PersonsAndAccounts\Infrastructure\Persistence\InMemoryIdentityLookup;
use Gamad\Core\PersonsAndAccounts\Infrastructure\Persistence\InMemoryPersonRepository;
use Gamad\Core\Shared\Application\DomainEventCollector;
use Gamad\Core\Tests\Support\InMemoryOutboxRepository;
use Gamad\Core\Tests\Support\SynchronousTransactionManager;
use PHPUnit\Framework\TestCase;

final class RegisterPersonHandlerTest extends TestCase
{
    public function test_it_registers_a_person_from_an_active_identity(): void
    {
        [$handler, $identities, $persons, $outbox] = $this->handler();
        $identities->register('GAM-PER-000001', 'person', 'active');

        $person = $handler(new RegisterPerson('GAM-PER-000001', 'Amina Traoré'));

        self::assertSame(PersonStatus::Active, $person->status());
        self::assertTrue($persons->exists(new PersonId('GAM-PER-000001')));
        self::assertCount(1, $outbox->messages);
    }

    public function test_it_rejects_an_identity_that_does_not_exist(): void
    {
        [$handler] = $this->handler();

        $this->expectException(IdentityNotEligibleForPerson::class);

        $handler(new RegisterPerson('GAM-PER-999999', 'Nobody'));
    }

    public function test_it_rejects_an_inactive_identity(): void
    {
        [$handler, $identities] = $this->handler();
        $identities->register('GAM-PER-000002', 'person', 'suspended');

        $this->expectException(IdentityNotEligibleForPerson::class);

        $handler(new RegisterPerson('GAM-PER-000002', 'Someone Suspended'));
    }

    public function test_it_rejects_an_identity_that_is_not_of_type_person(): void
    {
        [$handler, $identities] = $this->handler();
        $identities->register('GAM-SRV-000001', 'service', 'active');

        $this->expectException(IdentityNotEligibleForPerson::class);

        $handler(new RegisterPerson('GAM-SRV-000001', 'A Service'));
    }

    public function test_it_rejects_registering_the_same_person_twice(): void
    {
        [$handler, $identities] = $this->handler();
        $identities->register('GAM-PER-000001', 'person', 'active');
        $handler(new RegisterPerson('GAM-PER-000001', 'Amina Traoré'));

        $this->expectException(PersonAlreadyExists::class);

        $handler(new RegisterPerson('GAM-PER-000001', 'Amina Traoré'));
    }

    /** @return array{0: RegisterPersonHandler, 1: InMemoryIdentityLookup, 2: InMemoryPersonRepository, 3: InMemoryOutboxRepository} */
    private function handler(): array
    {
        $identities = new InMemoryIdentityLookup();
        $persons = new InMemoryPersonRepository();
        $outbox = new InMemoryOutboxRepository();

        $handler = new RegisterPersonHandler(
            identities: $identities,
            persons: $persons,
            persister: new AtomicPersonPersister($persons, $outbox, new DomainEventCollector(), new SynchronousTransactionManager()),
        );

        return [$handler, $identities, $persons, $outbox];
    }
}
