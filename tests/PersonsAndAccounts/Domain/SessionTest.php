<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\PersonsAndAccounts\Domain;

use DateTimeImmutable;
use Gamad\Core\PersonsAndAccounts\Domain\AuthenticationMethodId;
use Gamad\Core\PersonsAndAccounts\Domain\Event\SessionIssued;
use Gamad\Core\PersonsAndAccounts\Domain\Event\SessionRevoked;
use Gamad\Core\PersonsAndAccounts\Domain\Session;
use Gamad\Core\PersonsAndAccounts\Domain\SessionId;
use Gamad\Core\PersonsAndAccounts\Domain\UserAccountId;
use PHPUnit\Framework\TestCase;

final class SessionTest extends TestCase
{
    public function test_it_issues_a_session_and_records_the_event(): void
    {
        $issuedAt = new DateTimeImmutable('2026-07-14T00:00:00+00:00');
        $expiresAt = $issuedAt->modify('+1 hour');

        $session = Session::issue(
            SessionId::generate(),
            UserAccountId::generate(),
            AuthenticationMethodId::generate(),
            hash('sha256', 'raw-token'),
            $expiresAt,
            $issuedAt,
        );

        self::assertFalse($session->isRevoked());
        self::assertTrue($session->isActive($issuedAt));
        self::assertInstanceOf(SessionIssued::class, $session->releaseEvents()[0]);
    }

    public function test_it_is_expired_after_its_expiry_instant(): void
    {
        $issuedAt = new DateTimeImmutable('2026-07-14T00:00:00+00:00');
        $expiresAt = $issuedAt->modify('+1 hour');
        $session = Session::issue(SessionId::generate(), UserAccountId::generate(), AuthenticationMethodId::generate(), hash('sha256', 'x'), $expiresAt, $issuedAt);

        self::assertTrue($session->isExpired($expiresAt->modify('+1 second')));
        self::assertFalse($session->isActive($expiresAt->modify('+1 second')));
    }

    public function test_revoking_records_the_event_once(): void
    {
        $session = Session::issue(SessionId::generate(), UserAccountId::generate(), AuthenticationMethodId::generate(), hash('sha256', 'x'), new DateTimeImmutable('+1 hour'));
        $session->releaseEvents();

        $session->revoke('manual_logout');

        self::assertTrue($session->isRevoked());
        self::assertInstanceOf(SessionRevoked::class, $session->releaseEvents()[0]);
    }

    public function test_revoking_an_already_revoked_session_is_idempotent(): void
    {
        $session = Session::issue(SessionId::generate(), UserAccountId::generate(), AuthenticationMethodId::generate(), hash('sha256', 'x'), new DateTimeImmutable('+1 hour'));
        $session->revoke('manual_logout');
        $session->releaseEvents();

        $session->revoke('identity_suspended');

        self::assertSame([], $session->releaseEvents());
    }
}
