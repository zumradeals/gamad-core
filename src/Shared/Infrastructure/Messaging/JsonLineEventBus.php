<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Infrastructure\Messaging;

use Gamad\Core\Shared\Messaging\EventBus;
use Gamad\Core\Shared\Outbox\PendingOutboxMessage;
use JsonException;

final readonly class JsonLineEventBus implements EventBus
{
    /** @param resource $stream */
    public function __construct(private mixed $stream)
    {
    }

    /** @throws JsonException */
    public function publish(PendingOutboxMessage $message): void
    {
        $line = json_encode([
            'message_id' => $message->id,
            'aggregate_id' => $message->aggregateId,
            'event_name' => $message->eventName,
            'payload' => $message->payload,
            'occurred_at' => $message->occurredAt->format(DATE_ATOM),
            'recorded_at' => $message->recordedAt->format(DATE_ATOM),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        fwrite($this->stream, $line . PHP_EOL);
    }
}
