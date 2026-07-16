<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\AccessControl\Http;

use PHPUnit\Framework\TestCase;

final class OpenApiAccessControlContractTest extends TestCase
{
    public function test_contract_contains_all_operations(): void
    {
        $contract = (string) file_get_contents(__DIR__ . '/../../../openapi/access-control-v1.yaml');

        foreach ([
            'createPermission',
            'listPermissions',
            'createRole',
            'listRoles',
            'addPermissionToRole',
            'assignRole',
            'revokeRole',
            'evaluateAccess',
        ] as $required) {
            self::assertStringContainsString($required, $contract);
        }
    }

    public function test_contract_declares_openapi_31_and_bearer_session_security(): void
    {
        $contract = (string) file_get_contents(__DIR__ . '/../../../openapi/access-control-v1.yaml');

        self::assertStringContainsString('openapi: 3.1.0', $contract);
        self::assertStringContainsString('scheme: bearer', $contract);
    }

    public function test_no_operation_is_public(): void
    {
        $contract = (string) file_get_contents(__DIR__ . '/../../../openapi/access-control-v1.yaml');

        self::assertStringNotContainsString('security: []', $contract);
    }
}
