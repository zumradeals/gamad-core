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
        if (!is_array($payload)) {
            throw new RuntimeException('Response payload must be a JSON object or array.');
        }

        if ($response->status >= 400) {
            if (!isset($payload['error']) && !isset($payload['type'])) {
                throw new RuntimeException('Error response must expose a stable error identifier.');
            }
            return;
        }

        $required = match ($operationId) {
            'getRuntimeHealthSummary' => ['healthy', 'live_workers', 'ready_workers', 'stale_workers', 'workers'],
            'getOutboxDashboard' => ['pending', 'locked', 'published', 'dead_letters'],
            'listDeadLetters' => [],
            'inspectDeadLetter' => ['id', 'aggregate_id', 'event_name', 'payload', 'attempts', 'last_error', 'failed_at'],
            'replayDeadLetter' => ['replayed'],
            'registerIdentity', 'getIdentity', 'transitionIdentity' => ['identity_id', 'identity_type', 'status', 'registered_at'],
            default => throw new RuntimeException(sprintf('Unknown OpenAPI operation %s.', $operationId)),
        };

        foreach ($required as $field) {
            if (!array_key_exists($field, $payload)) {
                throw new RuntimeException(sprintf('Response field %s is required.', $field));
            }
        }
    }
}
