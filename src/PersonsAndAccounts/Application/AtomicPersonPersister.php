<?php

declare(strict_types=1);

namespace Gamad\Core\PersonsAndAccounts\Application;

use Gamad\Core\PersonsAndAccounts\Domain\Person;
use Gamad\Core\PersonsAndAccounts\Domain\PersonRepository;
use Gamad\Core\Shared\Application\DomainEventCollector;
use Gamad\Core\Shared\Application\TransactionManager;
use Gamad\Core\Shared\Outbox\OutboxMessage;
use Gamad\Core\Shared\Outbox\OutboxRepository;

final readonly class AtomicPersonPersister
{
    public function __construct(
        private PersonRepository $persons,
        private OutboxRepository $outbox,
        private DomainEventCollector $events,
        private TransactionManager $transactions,
    ) {
    }

    public function persist(Person $person): void
    {
        $events = $this->events->collect($person);

        $this->transactions->transactional(function () use ($person, $events): void {
            $this->persons->save($person);

            foreach ($events as $event) {
                $this->outbox->append(OutboxMessage::fromDomainEvent($event));
            }
        });

        $this->events->clear($person);
    }
}
