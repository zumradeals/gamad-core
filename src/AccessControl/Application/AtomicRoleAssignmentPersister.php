<?php

declare(strict_types=1);

namespace Gamad\Core\AccessControl\Application;

use Gamad\Core\AccessControl\Domain\RoleAssignment;
use Gamad\Core\AccessControl\Domain\RoleAssignmentRepository;
use Gamad\Core\Shared\Application\DomainEventCollector;
use Gamad\Core\Shared\Application\TransactionManager;
use Gamad\Core\Shared\Outbox\OutboxMessage;
use Gamad\Core\Shared\Outbox\OutboxRepository;

final readonly class AtomicRoleAssignmentPersister
{
    public function __construct(
        private RoleAssignmentRepository $assignments,
        private OutboxRepository $outbox,
        private DomainEventCollector $events,
        private TransactionManager $transactions,
    ) {
    }

    public function persist(RoleAssignment $assignment): void
    {
        $events = $this->events->collect($assignment);

        $this->transactions->transactional(function () use ($assignment, $events): void {
            $this->assignments->save($assignment);

            foreach ($events as $event) {
                $this->outbox->append(OutboxMessage::fromDomainEvent($event));
            }
        });

        $this->events->clear($assignment);
    }
}
