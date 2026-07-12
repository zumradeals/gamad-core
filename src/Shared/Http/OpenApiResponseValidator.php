<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Http;

use RuntimeException;

final readonly class OpenApiResponseValidator
{
    public function validate(string $operationId, Response $response): void
    {
        if ($response->body === '') {
            throw new RuntimeException('JSON response body cannot be empty.');
        }

        $payload = json_decode($response->body, true, flags: JSON_THROW_ON_ERROR);

        if ($response->status >= 400) {
            if (!is_array($payload) || !isset($payload['type'], $payload['title'], $payload['status'], $payload['detail'], $payload['request_id'])) {
                throw new RuntimeException('Error response does not match Problem Details contract.');
            }
            return;
        }

        $required = match ($operationId) {
            'getRuntimeHealthSummary' => ['healthy', 'live_workers', 'ready_workers', 'stale_workers', 'workers'],
            'getOutboxDashboard' => ['pending', 'locked', 'published', 'dead_letters'],
            'listDeadLetters' => [],
            'inspectDeadLetter' => ['id', 'aggregate_id', 'event_name', 'payload', 'attempts', 'last_error', 'failed_at'],
            'replayDeadLetter' => ['replayed'],
            default => throw new RuntimeException(sprintf('Unknown OpenAPI operation %s.', $operationId)),
        };

        if (!is_array($payload)) {
            throw new RuntimeException('Response payload must be a JSON object or array.');
        }

        foreach ($required as $field) {
            if (!array_key_exists($field, $payload)) {
                throw new RuntimeException(sprintf('Response field %s is required.', $field));
            }
        }
    }
}
