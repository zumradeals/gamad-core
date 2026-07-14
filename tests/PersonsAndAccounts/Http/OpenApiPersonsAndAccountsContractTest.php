<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\PersonsAndAccounts\Http;

use PHPUnit\Framework\TestCase;

final class OpenApiPersonsAndAccountsContractTest extends TestCase
{
    public function test_contract_contains_all_operations(): void
    {
        $contract = (string) file_get_contents(__DIR__ . '/../../../openapi/persons-and-accounts-v1.yaml');

        foreach ([
            'registerPerson',
            'getPerson',
            'registerUserAccount',
            'setPassword',
            'login',
            'revokeSession',
        ] as $required) {
            self::assertStringContainsString($required, $contract);
        }
    }

    public function test_contract_declares_openapi_31_and_bearer_session_security(): void
    {
        $contract = (string) file_get_contents(__DIR__ . '/../../../openapi/persons-and-accounts-v1.yaml');

        self::assertStringContainsString('openapi: 3.1.0', $contract);
        self::assertStringContainsString('scheme: bearer', $contract);
    }

    public function test_login_is_the_only_public_operation(): void
    {
        $contract = (string) file_get_contents(__DIR__ . '/../../../openapi/persons-and-accounts-v1.yaml');

        self::assertMatchesRegularExpression('/operationId: login\s+security: \[\]/', $contract);
        self::assertStringNotContainsString('security: []', str_replace("operationId: login\n      security: []", '', $contract));
    }
}
