<?php

declare(strict_types=1);

namespace Gamad\Core\PersonsAndAccounts\Http;

use Gamad\Core\Shared\Http\Response;
use RuntimeException;

/**
 * Deliberately not an extension of Shared's OpenApiResponseValidator — that
 * class must never learn this context's vocabulary (ADR-0013 §2).
 */
final readonly class PersonsAndAccountsResponseValidator
{
    public function validate(string $operationId, Response $response): void
    {
        if ($response->body === '' && $response->status !== 204) {
            throw new RuntimeException('JSON response body cannot be empty.');
        }

        if ($response->status === 204) {
            return;
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
            'registerPerson', 'getPerson' => ['person_id', 'declared_name', 'status', 'registered_at'],
            'registerUserAccount' => ['account_id', 'person_id', 'status', 'created_at'],
            'login' => ['token', 'expires_at'],
            default => throw new RuntimeException(sprintf('Unknown OpenAPI operation %s.', $operationId)),
        };

        foreach ($required as $field) {
            if (!array_key_exists($field, $payload)) {
                throw new RuntimeException(sprintf('Response field %s is required.', $field));
            }
        }
    }
}
