<?php

declare(strict_types=1);

namespace Gamad\Core\PersonsAndAccounts\Application\Command;

use DateTimeImmutable;
use Gamad\Core\PersonsAndAccounts\Application\AtomicSessionPersister;
use Gamad\Core\PersonsAndAccounts\Application\AuthenticationResult;
use Gamad\Core\PersonsAndAccounts\Application\Exception\AuthenticationFailed;
use Gamad\Core\PersonsAndAccounts\Domain\PersonId;
use Gamad\Core\PersonsAndAccounts\Domain\Session;
use Gamad\Core\PersonsAndAccounts\Domain\SessionId;
use Gamad\Core\PersonsAndAccounts\Domain\UserAccountRepository;
use Gamad\Core\PersonsAndAccounts\Domain\UserAccountStatus;

/**
 * Never reveals, in its outcome, whether the failure was "no such account"
 * or "wrong password" (Task 4, ADR-0018). A fixed dummy hash is checked
 * even when no account/method exists, so a missing account does not
 * resolve measurably faster than a wrong password on a real one.
 */
final readonly class AuthenticateHandler
{
    private const DUMMY_HASH = '$argon2id$v=19$m=65536,t=4,p=1$bzZFVUE4RS40Q0ZjUjc0dQ$s/HMW3mt91go9u/i+DFCm+lmHiqMNLf2k9WpNI5kQ7g';

    public function __construct(
        private UserAccountRepository $accounts,
        private AtomicSessionPersister $persister,
        private int $sessionTtlSeconds = 3600,
    ) {
    }

    public function __invoke(Authenticate $command): AuthenticationResult
    {
        $account = $this->accounts->findByPersonId(new PersonId($command->personId));
        $method = $account?->currentPasswordMethod();

        $hashToVerify = $method?->credentialRef() ?? self::DUMMY_HASH;
        $passwordMatches = password_verify($command->plainPassword, $hashToVerify);

        $accountUsable = $account !== null
            && $method !== null
            && $account->status() === UserAccountStatus::Active;

        if (!$accountUsable || !$passwordMatches) {
            throw AuthenticationFailed::invalidCredentials();
        }

        $now = new DateTimeImmutable();
        $rawToken = bin2hex(random_bytes(32));

        $session = Session::issue(
            SessionId::generate(),
            $account->id(),
            $method->id(),
            hash('sha256', $rawToken),
            $now->modify(sprintf('+%d seconds', $this->sessionTtlSeconds)),
            $now,
        );

        $this->persister->persist($session);

        return new AuthenticationResult($session, $rawToken);
    }
}
