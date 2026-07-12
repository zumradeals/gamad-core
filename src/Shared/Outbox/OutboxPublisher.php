<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Outbox;

use DateInterval;
use DateTimeImmutable;
use Gamad\Core\Shared\Messaging\EventBus;
use Throwable;

final readonly class OutboxPublisher
{
    public function __construct(
        private OutboxRepository $outbox,
        private EventBus $eventBus,
        private RetryPolicy $retryPolicy,
        private string $workerId,
        private int $batchSize = 100,
        private int $lockSeconds = 60,
    ) {
    }

    public function publishPending(?DateTimeImmutable $now = null): OutboxPublishReport
    {
        $now ??= new DateTimeImmutable();
        $lockedUntil = $now->add(new DateInterval(sprintf('PT%dS', $this->lockSeconds)));
        $messages = $this->outbox->claimPending($this->batchSize, $this->workerId, $lockedUntil);

        $published = 0;
        $retried = 0;
        $deadLettered = 0;

        foreach ($messages as $message) {
            try {
                $this->eventBus->publish($message);
                $this->outbox->markPublished($message->id, $now);
                ++$published;
            } catch (Throwable $exception) {
                $attempts = $message->attempts + 1;
                $error = mb_substr($exception->getMessage(), 0, 4000);

                if ($this->retryPolicy->mustDeadLetter($attempts)) {
                    $this->outbox->moveToDeadLetter($message, $error, $now);
                    ++$deadLettered;
                    continue;
                }

                $this->outbox->markFailed(
                    messageId: $message->id,
                    attempts: $attempts,
                    error: $error,
                    availableAt: $this->retryPolicy->nextAvailableAt($now, $attempts),
                );
                ++$retried;
            }
        }

        return new OutboxPublishReport(
            claimed: count($messages),
            published: $published,
            retried: $retried,
            deadLettered: $deadLettered,
        );
    }
}
