<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\PersonsAndAccounts\Domain;

use DateTimeImmutable;
use DomainException;
use Gamad\Core\PersonsAndAccounts\Domain\AuthenticationMethodId;
use Gamad\Core\PersonsAndAccounts\Domain\AuthenticationMethodType;
use Gamad\Core\PersonsAndAccounts\Domain\Event\AuthenticationMethodAdded;
use Gamad\Core\PersonsAndAccounts\Domain\Event\UserAccountCreated;
use Gamad\Core\PersonsAndAccounts\Domain\PersonId;
use Gamad\Core\PersonsAndAccounts\Domain\UserAccount;
use Gamad\Core\PersonsAndAccounts\Domain\UserAccountId;
use Gamad\Core\PersonsAndAccounts\Domain\UserAccountStatus;
use PHPUnit\Framework\TestCase;

final class UserAccountTest extends TestCase
{
    public function test_it_creates_an_active_account_for_a_person(): void
    {
        $personId = new PersonId('GAM-GAT-PER-000001');
        $accountId = UserAccountId::generate();

        $account = UserAccount::create($accountId, $personId);

        self::assertTrue($account->id()->equals($accountId));
        self::assertTrue($account->personId()->equals($personId));
        self::assertSame(UserAccountStatus::Active, $account->status());
        self::assertInstanceOf(UserAccountCreated::class, $account->releaseEvents()[0]);
    }

    public function test_it_allows_active_to_suspended_and_back(): void
    {
        $account = UserAccount::create(UserAccountId::generate(), new PersonId('GAM-GAT-PER-000001'));

        $account->suspend();
        self::assertSame(UserAccountStatus::Suspended, $account->status());

        $account->activate();
        self::assertSame(UserAccountStatus::Active, $account->status());
    }

    public function test_disabled_is_a_terminal_status(): void
    {
        $account = UserAccount::create(UserAccountId::generate(), new PersonId('GAM-GAT-PER-000001'));
        $account->disable();

        $this->expectException(DomainException::class);

        $account->activate();
    }

    public function test_it_records_an_authentication_method_addition(): void
    {
        $account = UserAccount::create(UserAccountId::generate(), new PersonId('GAM-GAT-PER-000001'));
        $account->releaseEvents();

        $methodId = AuthenticationMethodId::generate();
        $account->addAuthenticationMethod($methodId, AuthenticationMethodType::Password, 'argon2id$hash');

        self::assertCount(1, $account->authenticationMethods());
        self::assertInstanceOf(AuthenticationMethodAdded::class, $account->releaseEvents()[0]);
    }

    public function test_it_rejects_adding_a_method_to_a_suspended_account(): void
    {
        $account = UserAccount::create(UserAccountId::generate(), new PersonId('GAM-GAT-PER-000001'));
        $account->suspend();

        $this->expectException(DomainException::class);

        $account->addAuthenticationMethod(AuthenticationMethodId::generate(), AuthenticationMethodType::Password, 'argon2id$hash');
    }

    public function test_current_password_method_is_the_most_recently_added_one(): void
    {
        $account = UserAccount::create(UserAccountId::generate(), new PersonId('GAM-GAT-PER-000001'));

        $account->addAuthenticationMethod(
            AuthenticationMethodId::generate(),
            AuthenticationMethodType::Password,
            'old-hash',
            new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
        );
        $account->addAuthenticationMethod(
            AuthenticationMethodId::generate(),
            AuthenticationMethodType::Password,
            'new-hash',
            new DateTimeImmutable('2026-07-14T00:00:00+00:00'),
        );

        self::assertSame('new-hash', $account->currentPasswordMethod()?->credentialRef());
    }
}
