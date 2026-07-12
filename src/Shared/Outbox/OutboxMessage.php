<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Outbox;

use DateTimeImmutable;
use Gamad\Core\Shared\Domain\DomainEvent;
use JsonException;

final readonly class OutboxMessage
{
    /** @param array<string, bool|float|int|string|null> $payload */
    public function __construct(
        public string $id,
        public string $aggregateId,
        public string $eventName,
        public array $payload,
        public DateTimeImmutable $occurredAt,
        public DateTimeImmutable $recordedAt,
    ) {
    }

    public static function fromDomainEvent(
        DomainEvent $event,
        ?DateTimeImmutable $recordedAt = null,
    ): self {
        return new self(
            id: self::generateId(),
            aggregateId: $event->aggregateId(),
            eventName: $event->eventName(),
            payload: $event->payload(),
            occurredAt: $event->occurredAt(),
            recordedAt: $recordedAt ?? new DateTimeImmutable(),
        );
    }

    /** @throws JsonException */
    public function payloadAsJson(): string
    {
        return json_encode($this->payload, JSON_THROW_ON_ERROR);
    }

    private static function generateId(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }
}
