<?php

declare(strict_types=1);

namespace Gamad\Core\PersonsAndAccounts\Application;

use Gamad\Core\PersonsAndAccounts\Domain\Session;
use Gamad\Core\PersonsAndAccounts\Domain\SessionRepository;
use Gamad\Core\Shared\Application\DomainEventCollector;
use Gamad\Core\Shared\Application\TransactionManager;
use Gamad\Core\Shared\Outbox\OutboxMessage;
use Gamad\Core\Shared\Outbox\OutboxRepository;

final readonly class AtomicSessionPersister
{
    public function __construct(
        private SessionRepository $sessions,
        private OutboxRepository $outbox,
        private DomainEventCollector $events,
        private TransactionManager $transactions,
    ) {
    }

    public function persist(Session $session): void
    {
        $events = $this->events->collect($session);

        $this->transactions->transactional(function () use ($session, $events): void {
            $this->sessions->save($session);

            foreach ($events as $event) {
                $this->outbox->append(OutboxMessage::fromDomainEvent($event));
            }
        });

        $this->events->clear($session);
    }
}
