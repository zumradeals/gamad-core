<?php

declare(strict_types=1);

namespace Gamad\Core\OrganizationsAndMemberships\Application;

use Gamad\Core\OrganizationsAndMemberships\Domain\Membership;
use Gamad\Core\OrganizationsAndMemberships\Domain\MembershipRepository;
use Gamad\Core\Shared\Application\DomainEventCollector;
use Gamad\Core\Shared\Application\TransactionManager;
use Gamad\Core\Shared\Outbox\OutboxMessage;
use Gamad\Core\Shared\Outbox\OutboxRepository;

final readonly class AtomicMembershipPersister
{
    public function __construct(
        private MembershipRepository $memberships,
        private OutboxRepository $outbox,
        private DomainEventCollector $events,
        private TransactionManager $transactions,
    ) {
    }

    public function persist(Membership $membership): void
    {
        $events = $this->events->collect($membership);

        $this->transactions->transactional(function () use ($membership, $events): void {
            $this->memberships->save($membership);

            foreach ($events as $event) {
                $this->outbox->append(OutboxMessage::fromDomainEvent($event));
            }
        });

        $this->events->clear($membership);
    }
}
