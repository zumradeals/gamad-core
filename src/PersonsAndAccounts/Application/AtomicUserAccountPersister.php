<?php

declare(strict_types=1);

namespace Gamad\Core\PersonsAndAccounts\Application;

use Gamad\Core\PersonsAndAccounts\Domain\UserAccount;
use Gamad\Core\PersonsAndAccounts\Domain\UserAccountRepository;
use Gamad\Core\Shared\Application\DomainEventCollector;
use Gamad\Core\Shared\Application\TransactionManager;
use Gamad\Core\Shared\Outbox\OutboxMessage;
use Gamad\Core\Shared\Outbox\OutboxRepository;

final readonly class AtomicUserAccountPersister
{
    public function __construct(
        private UserAccountRepository $accounts,
        private OutboxRepository $outbox,
        private DomainEventCollector $events,
        private TransactionManager $transactions,
    ) {
    }

    public function persist(UserAccount $account): void
    {
        $events = $this->events->collect($account);

        $this->transactions->transactional(function () use ($account, $events): void {
            $this->accounts->save($account);

            foreach ($events as $event) {
                $this->outbox->append(OutboxMessage::fromDomainEvent($event));
            }
        });

        $this->events->clear($account);
    }
}
