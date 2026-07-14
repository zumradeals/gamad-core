<?php

declare(strict_types=1);

namespace Gamad\Core\PersonsAndAccounts\Application\Command;

use Gamad\Core\PersonsAndAccounts\Application\AtomicUserAccountPersister;
use Gamad\Core\PersonsAndAccounts\Application\Exception\UserAccountNotFound;
use Gamad\Core\PersonsAndAccounts\Domain\AuthenticationMethodId;
use Gamad\Core\PersonsAndAccounts\Domain\AuthenticationMethodType;
use Gamad\Core\PersonsAndAccounts\Domain\UserAccountId;
use Gamad\Core\PersonsAndAccounts\Domain\UserAccountRepository;
use InvalidArgumentException;

/**
 * Hashes with Argon2id (ADR-0018 §1). The plaintext password is never
 * passed to a logger, an exception message, or the audit trail — it lives
 * only in the SetPassword command and is discarded once hashed.
 */
final readonly class SetPasswordHandler
{
    public function __construct(
        private UserAccountRepository $accounts,
        private AtomicUserAccountPersister $persister,
    ) {
    }

    public function __invoke(SetPassword $command): void
    {
        if ($command->plainPassword === '') {
            throw new InvalidArgumentException('Password cannot be empty.');
        }

        $account = $this->accounts->findById(new UserAccountId($command->accountId));
        if ($account === null) {
            throw UserAccountNotFound::withId($command->accountId);
        }

        $hash = password_hash($command->plainPassword, PASSWORD_ARGON2ID);

        $account->addAuthenticationMethod(AuthenticationMethodId::generate(), AuthenticationMethodType::Password, $hash);
        $this->persister->persist($account);
    }
}
