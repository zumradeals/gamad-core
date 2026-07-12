<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\Shared\Http;

use PHPUnit\Framework\TestCase;

final class OpenApiAdministrativeContractTest extends TestCase
{
    public function test_contract_contains_all_runtime_operations_and_scopes(): void
    {
        $contract = (string) file_get_contents(__DIR__ . '/../../../openapi/admin-runtime-v1.yaml');

        foreach ([
            'getRuntimeHealthSummary',
            'getOutboxDashboard',
            'listDeadLetters',
            'inspectDeadLetter',
            'replayDeadLetter',
            'core.runtime.health.read',
            'core.outbox.dashboard.read',
            'core.outbox.dead_letter.read',
            'core.outbox.dead_letter.replay',
        ] as $required) {
            self::assertStringContainsString($required, $contract);
        }
    }

    public function test_contract_declares_openapi_31_and_bearer_oauth_security(): void
    {
        $contract = (string) file_get_contents(__DIR__ . '/../../../openapi/admin-runtime-v1.yaml');

        self::assertStringContainsString('openapi: 3.1.0', $contract);
        self::assertStringContainsString('type: oauth2', $contract);
        self::assertStringContainsString('clientCredentials:', $contract);
    }
}
