<?php

declare(strict_types=1);

namespace Gamad\Core\PersonsAndAccounts\Application\Command;

use Gamad\Core\PersonsAndAccounts\Application\AtomicUserAccountPersister;
use Gamad\Core\PersonsAndAccounts\Application\Exception\PersonNotFound;
use Gamad\Core\PersonsAndAccounts\Application\Exception\UserAccountAlreadyExists;
use Gamad\Core\PersonsAndAccounts\Domain\PersonId;
use Gamad\Core\PersonsAndAccounts\Domain\PersonRepository;
use Gamad\Core\PersonsAndAccounts\Domain\UserAccount;
use Gamad\Core\PersonsAndAccounts\Domain\UserAccountId;
use Gamad\Core\PersonsAndAccounts\Domain\UserAccountRepository;
use Gamad\Core\Shared\Contract\AccessControlGateway;
use Gamad\Core\Shared\Contract\AccessDenied;
use Gamad\Core\Shared\Contract\IdentityId as ContractIdentityId;
use InvalidArgumentException;

final readonly class RegisterUserAccountHandler
{
    public function __construct(
        private PersonRepository $persons,
        private UserAccountRepository $accounts,
        private AtomicUserAccountPersister $persister,
        private AccessControlGateway $accessControl,
    ) {
    }

    public function __invoke(RegisterUserAccount $command): UserAccount
    {
        // Self-provisioning by default, same rationale as
        // RegisterPersonHandler (ADR-0021 Task 8).
        try {
            $actor = new ContractIdentityId($command->actorId ?? $command->personId);
            $context = new ContractIdentityId($command->personId);
            $decision = $this->accessControl->can($actor, 'account:create', $context);
            if (!$decision->allowed) {
                throw AccessDenied::forDecision('account:create', $decision->reason);
            }
        } catch (InvalidArgumentException) {
        }

        $personId = new PersonId($command->personId);
        if (!$this->persons->exists($personId)) {
            throw PersonNotFound::withId($command->personId);
        }

        // Defense in depth: the database also enforces this via a unique
        // constraint on user_accounts.person_id (GENESIS-010 §A).
        if ($this->accounts->existsForPerson($personId)) {
            throw UserAccountAlreadyExists::forPerson($command->personId);
        }

        $account = UserAccount::create(UserAccountId::generate(), $personId, $command->createdAt);
        $this->persister->persist($account);

        return $account;
    }
}
