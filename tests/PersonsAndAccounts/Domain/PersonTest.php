<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\PersonsAndAccounts\Domain;

use DateTimeImmutable;
use DomainException;
use Gamad\Core\PersonsAndAccounts\Domain\Event\PersonRegistered;
use Gamad\Core\PersonsAndAccounts\Domain\Person;
use Gamad\Core\PersonsAndAccounts\Domain\PersonId;
use Gamad\Core\PersonsAndAccounts\Domain\PersonStatus;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PersonTest extends TestCase
{
    public function test_it_registers_an_active_person(): void
    {
        $registeredAt = new DateTimeImmutable('2026-07-14T00:00:00+00:00');
        $id = new PersonId('GAM-PER-000001');

        $person = Person::register($id, 'Amina Traoré', $registeredAt);

        self::assertTrue($person->id()->equals($id));
        self::assertSame('Amina Traoré', $person->declaredName());
        self::assertSame(PersonStatus::Active, $person->status());
        self::assertInstanceOf(PersonRegistered::class, $person->releaseEvents()[0]);
    }

    public function test_person_id_rejects_a_malformed_identifier(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PersonId('not-an-identity');
    }

    public function test_it_allows_active_to_inactive_transition(): void
    {
        $person = Person::register(new PersonId('GAM-PER-000001'), 'Amina Traoré');

        $person->transitionTo(PersonStatus::Inactive);

        self::assertSame(PersonStatus::Inactive, $person->status());
    }

    public function test_it_allows_inactive_back_to_active_transition(): void
    {
        $person = Person::register(new PersonId('GAM-PER-000001'), 'Amina Traoré');
        $person->transitionTo(PersonStatus::Inactive);

        $person->transitionTo(PersonStatus::Active);

        self::assertSame(PersonStatus::Active, $person->status());
    }

    public function test_deceased_is_a_terminal_status(): void
    {
        $person = Person::register(new PersonId('GAM-PER-000001'), 'Amina Traoré');
        $person->transitionTo(PersonStatus::Deceased);

        $this->expectException(DomainException::class);

        $person->transitionTo(PersonStatus::Active);
    }
}
