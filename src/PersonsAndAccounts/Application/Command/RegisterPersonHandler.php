<?php

declare(strict_types=1);

namespace Gamad\Core\PersonsAndAccounts\Application\Command;

use Gamad\Core\PersonsAndAccounts\Application\AtomicPersonPersister;
use Gamad\Core\PersonsAndAccounts\Application\Exception\IdentityNotEligibleForPerson;
use Gamad\Core\PersonsAndAccounts\Application\Exception\PersonAlreadyExists;
use Gamad\Core\PersonsAndAccounts\Application\IdentityLookup;
use Gamad\Core\PersonsAndAccounts\Domain\Person;
use Gamad\Core\PersonsAndAccounts\Domain\PersonId;
use Gamad\Core\PersonsAndAccounts\Domain\PersonRepository;
use Gamad\Core\Shared\Contract\AccessControlGateway;
use Gamad\Core\Shared\Contract\AccessDenied;
use Gamad\Core\Shared\Contract\IdentityId as ContractIdentityId;
use InvalidArgumentException;

/**
 * Verifies eligibility entirely here, in Application — Person itself is
 * built without knowing anything about the Identity Registry (Task 3).
 */
final readonly class RegisterPersonHandler
{
    public function __construct(
        private IdentityLookup $identities,
        private PersonRepository $persons,
        private AtomicPersonPersister $persister,
        private AccessControlGateway $accessControl,
    ) {
    }

    public function __invoke(RegisterPerson $command): Person
    {
        // No natural actor exists for a self-registration flow — falls back
        // to the identity being registered, itself as context, same
        // rationale as RegisterIdentityHandler (ADR-0021 Task 2).
        try {
            $actor = new ContractIdentityId($command->actorId ?? $command->identityId);
            $context = new ContractIdentityId($command->identityId);
            $decision = $this->accessControl->can($actor, 'person:create', $context);
            if (!$decision->allowed) {
                throw AccessDenied::forDecision('person:create', $decision->reason);
            }
        } catch (InvalidArgumentException) {
        }

        $identity = $this->identities->find($command->identityId);
        if ($identity === null) {
            // Not found in this Core's own Identity Registry — by construction
            // (ADR-0017 §3), that also covers "belongs to a different realm":
            // this instance never stores an identity it did not itself emit.
            throw IdentityNotEligibleForPerson::notFound($command->identityId);
        }
        if ($identity->type !== 'person') {
            throw IdentityNotEligibleForPerson::wrongType($command->identityId, $identity->type);
        }
        if ($identity->status !== 'active') {
            throw IdentityNotEligibleForPerson::notActive($command->identityId, $identity->status);
        }

        $personId = new PersonId($command->identityId);
        if ($this->persons->exists($personId)) {
            throw PersonAlreadyExists::withId($command->identityId);
        }

        $person = Person::register($personId, $command->declaredName, $command->registeredAt, $command->contact);
        $this->persister->persist($person);

        return $person;
    }
}
