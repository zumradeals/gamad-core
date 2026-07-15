<?php

declare(strict_types=1);

namespace Gamad\Core\OrganizationsAndMemberships\Application;

use Gamad\Core\OrganizationsAndMemberships\Domain\Organization;
use Gamad\Core\OrganizationsAndMemberships\Domain\OrganizationRepository;
use Gamad\Core\Shared\Application\DomainEventCollector;
use Gamad\Core\Shared\Application\TransactionManager;
use Gamad\Core\Shared\Outbox\OutboxMessage;
use Gamad\Core\Shared\Outbox\OutboxRepository;

final readonly class AtomicOrganizationPersister
{
    public function __construct(
        private OrganizationRepository $organizations,
        private OutboxRepository $outbox,
        private DomainEventCollector $events,
        private TransactionManager $transactions,
    ) {
    }

    public function persist(Organization $organization): void
    {
        $events = $this->events->collect($organization);

        $this->transactions->transactional(function () use ($organization, $events): void {
            $this->organizations->save($organization);

            foreach ($events as $event) {
                $this->outbox->append(OutboxMessage::fromDomainEvent($event));
            }
        });

        $this->events->clear($organization);
    }
}
