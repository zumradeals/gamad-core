<?php

declare(strict_types=1);

namespace Gamad\Core\IdentityRegistry\Application;

use Gamad\Core\IdentityRegistry\Domain\Identity;
use Gamad\Core\IdentityRegistry\Domain\IdentityRepository;
use Gamad\Core\Shared\Application\DomainEventCollector;
use Gamad\Core\Shared\Application\TransactionManager;
use Gamad\Core\Shared\Outbox\OutboxMessage;
use Gamad\Core\Shared\Outbox\OutboxRepository;

final readonly class AtomicIdentityPersister
{
    public function __construct(
        private IdentityRepository $identities,
        private OutboxRepository $outbox,
        private DomainEventCollector $events,
        private TransactionManager $transactions,
    ) {
    }

    public function persist(Identity $identity): void
    {
        $events = $this->events->collect($identity);

        $this->transactions->transactional(function () use ($identity, $events): void {
            $this->identities->save($identity);

            foreach ($events as $event) {
                $this->outbox->append(OutboxMessage::fromDomainEvent($event));
            }
        });

        $this->events->clear($identity);
    }
}
