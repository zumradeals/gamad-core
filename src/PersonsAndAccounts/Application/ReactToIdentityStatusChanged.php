<?php

declare(strict_types=1);

namespace Gamad\Core\PersonsAndAccounts\Application;

use Gamad\Core\PersonsAndAccounts\Domain\PersonId;
use Gamad\Core\PersonsAndAccounts\Domain\PersonRepository;
use Gamad\Core\PersonsAndAccounts\Domain\SessionRepository;
use Gamad\Core\PersonsAndAccounts\Domain\UserAccountRepository;

/**
 * Task 8 — a suspended or revoked Identity must invalidate every active
 * Session of the associated UserAccount, reacting to the already-published
 * `identity.status_changed.v1` event — never by reading Identity Registry
 * tables directly (GENESIS-009 §7).
 *
 * A no-op for any identity that has no Person in this context (e.g. an
 * organization or a person never onboarded here), and for any status other
 * than suspended/revoked.
 */
final readonly class ReactToIdentityStatusChanged
{
    private const array TRIGGERING_STATUSES = ['suspended', 'revoked'];

    public function __construct(
        private PersonRepository $persons,
        private UserAccountRepository $accounts,
        private SessionRepository $sessions,
        private AtomicSessionPersister $persister,
    ) {
    }

    public function handle(string $identityId, string $newStatus): void
    {
        if (!in_array($newStatus, self::TRIGGERING_STATUSES, true)) {
            return;
        }

        $personId = new PersonId($identityId);
        if (!$this->persons->exists($personId)) {
            return;
        }

        $account = $this->accounts->findByPersonId($personId);
        if ($account === null) {
            return;
        }

        foreach ($this->sessions->findActiveByUserAccountId($account->id()) as $session) {
            $session->revoke('identity_' . $newStatus);
            $this->persister->persist($session);
        }
    }
}
