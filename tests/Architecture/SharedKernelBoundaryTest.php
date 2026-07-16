<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\Architecture;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Fitness functions for bounded-context dependency direction.
 *
 * ADR-0013 — Shared is a technical kernel and must never depend on a
 * bounded context such as IdentityRegistry.
 *
 * GENESIS-010 §C — Persons and User Accounts reads the Identity Registry;
 * the Identity Registry must never know Persons and User Accounts exists,
 * so it stays exploitable entirely on its own.
 */
final class SharedKernelBoundaryTest extends TestCase
{
    private const string SHARED_DIRECTORY = __DIR__ . '/../../src/Shared';
    private const string IDENTITY_REGISTRY_DIRECTORY = __DIR__ . '/../../src/IdentityRegistry';
    private const string PERSONS_AND_ACCOUNTS_DIRECTORY = __DIR__ . '/../../src/PersonsAndAccounts';
    private const string ORGANIZATIONS_AND_MEMBERSHIPS_DIRECTORY = __DIR__ . '/../../src/OrganizationsAndMemberships';
    private const string ACCESS_CONTROL_DIRECTORY = __DIR__ . '/../../src/AccessControl';

    private const string IDENTITY_REGISTRY_NAMESPACE_PREFIX = 'Gamad\\Core\\IdentityRegistry\\';
    private const string PERSONS_AND_ACCOUNTS_NAMESPACE_PREFIX = 'Gamad\\Core\\PersonsAndAccounts\\';
    private const string ORGANIZATIONS_AND_MEMBERSHIPS_NAMESPACE_PREFIX = 'Gamad\\Core\\OrganizationsAndMemberships\\';
    private const string ACCESS_CONTROL_NAMESPACE_PREFIX = 'Gamad\\Core\\AccessControl\\';

    public function test_shared_never_imports_identity_registry(): void
    {
        $violations = $this->findViolations(self::SHARED_DIRECTORY, self::IDENTITY_REGISTRY_NAMESPACE_PREFIX);

        self::assertSame(
            [],
            $violations,
            "The following files under src/Shared import Gamad\\Core\\IdentityRegistry\\*, "
            . "which violates ADR-0013 (Shared kernel boundary):\n"
            . implode("\n", $violations),
        );
    }

    public function test_identity_registry_never_imports_persons_and_accounts(): void
    {
        $violations = $this->findViolations(self::IDENTITY_REGISTRY_DIRECTORY, self::PERSONS_AND_ACCOUNTS_NAMESPACE_PREFIX);

        self::assertSame(
            [],
            $violations,
            "The following files under src/IdentityRegistry import Gamad\\Core\\PersonsAndAccounts\\*, "
            . "which violates GENESIS-010 §C (dependency runs one way only — "
            . "Persons and User Accounts reads the Identity Registry, never the reverse):\n"
            . implode("\n", $violations),
        );
    }

    /**
     * DIRECTIVE-006 Task 7 (extends ADR-0013) — Organizations and
     * Memberships reads both the Identity Registry and Persons and User
     * Accounts (GENESIS-012 §C), but only through its own Application-layer
     * ports (IdentityLookup, PersonLookup) backed by plain SQL in
     * Infrastructure — never by importing either context's own namespace.
     */
    public function test_organizations_and_memberships_never_imports_identity_registry(): void
    {
        $violations = $this->findViolations(self::ORGANIZATIONS_AND_MEMBERSHIPS_DIRECTORY, self::IDENTITY_REGISTRY_NAMESPACE_PREFIX);

        self::assertSame(
            [],
            $violations,
            "The following files under src/OrganizationsAndMemberships import Gamad\\Core\\IdentityRegistry\\*, "
            . "which violates ADR-0013 (Shared kernel boundary) as extended by DIRECTIVE-006 Task 7:\n"
            . implode("\n", $violations),
        );
    }

    public function test_organizations_and_memberships_never_imports_persons_and_accounts(): void
    {
        $violations = $this->findViolations(self::ORGANIZATIONS_AND_MEMBERSHIPS_DIRECTORY, self::PERSONS_AND_ACCOUNTS_NAMESPACE_PREFIX);

        self::assertSame(
            [],
            $violations,
            "The following files under src/OrganizationsAndMemberships import Gamad\\Core\\PersonsAndAccounts\\*, "
            . "which violates ADR-0013 (Shared kernel boundary) as extended by DIRECTIVE-006 Task 7:\n"
            . implode("\n", $violations),
        );
    }

    /**
     * DIRECTIVE-007 Task 8 (extends ADR-0013, GENESIS-014 §C) — Access
     * Control reads the Identity Registry, Persons and User Accounts, and
     * Organizations and Memberships (existence checks only, via its own
     * Application-layer ports backed by plain SQL), but never imports any
     * of their namespaces directly. Symmetrically, none of those three
     * contexts ever imports Gamad\Core\AccessControl\* — they all call
     * AccessControlGateway defined in Shared instead (ADR-0021).
     */
    public function test_access_control_never_imports_identity_registry(): void
    {
        $violations = $this->findViolations(self::ACCESS_CONTROL_DIRECTORY, self::IDENTITY_REGISTRY_NAMESPACE_PREFIX);

        self::assertSame(
            [],
            $violations,
            "The following files under src/AccessControl import Gamad\\Core\\IdentityRegistry\\*, "
            . "which violates ADR-0013 (Shared kernel boundary) as extended by DIRECTIVE-007 Task 8:\n"
            . implode("\n", $violations),
        );
    }

    public function test_access_control_never_imports_persons_and_accounts(): void
    {
        $violations = $this->findViolations(self::ACCESS_CONTROL_DIRECTORY, self::PERSONS_AND_ACCOUNTS_NAMESPACE_PREFIX);

        self::assertSame(
            [],
            $violations,
            "The following files under src/AccessControl import Gamad\\Core\\PersonsAndAccounts\\*, "
            . "which violates ADR-0013 (Shared kernel boundary) as extended by DIRECTIVE-007 Task 8:\n"
            . implode("\n", $violations),
        );
    }

    public function test_access_control_never_imports_organizations_and_memberships(): void
    {
        $violations = $this->findViolations(self::ACCESS_CONTROL_DIRECTORY, self::ORGANIZATIONS_AND_MEMBERSHIPS_NAMESPACE_PREFIX);

        self::assertSame(
            [],
            $violations,
            "The following files under src/AccessControl import Gamad\\Core\\OrganizationsAndMemberships\\*, "
            . "which violates ADR-0013 (Shared kernel boundary) as extended by DIRECTIVE-007 Task 8:\n"
            . implode("\n", $violations),
        );
    }

    public function test_identity_registry_never_imports_access_control(): void
    {
        $violations = $this->findViolations(self::IDENTITY_REGISTRY_DIRECTORY, self::ACCESS_CONTROL_NAMESPACE_PREFIX);

        self::assertSame(
            [],
            $violations,
            "The following files under src/IdentityRegistry import Gamad\\Core\\AccessControl\\*, "
            . "which violates ADR-0021 — every context calls AccessControlGateway defined in Shared instead:\n"
            . implode("\n", $violations),
        );
    }

    public function test_persons_and_accounts_never_imports_access_control(): void
    {
        $violations = $this->findViolations(self::PERSONS_AND_ACCOUNTS_DIRECTORY, self::ACCESS_CONTROL_NAMESPACE_PREFIX);

        self::assertSame(
            [],
            $violations,
            "The following files under src/PersonsAndAccounts import Gamad\\Core\\AccessControl\\*, "
            . "which violates ADR-0021 — every context calls AccessControlGateway defined in Shared instead:\n"
            . implode("\n", $violations),
        );
    }

    public function test_organizations_and_memberships_never_imports_access_control(): void
    {
        $violations = $this->findViolations(self::ORGANIZATIONS_AND_MEMBERSHIPS_DIRECTORY, self::ACCESS_CONTROL_NAMESPACE_PREFIX);

        self::assertSame(
            [],
            $violations,
            "The following files under src/OrganizationsAndMemberships import Gamad\\Core\\AccessControl\\*, "
            . "which violates ADR-0021 — every context calls AccessControlGateway defined in Shared instead:\n"
            . implode("\n", $violations),
        );
    }

    /** @return list<string> */
    private function findViolations(string $directory, string $forbiddenNamespacePrefix): array
    {
        $violations = [];

        foreach ($this->phpFilesUnder($directory) as $file) {
            $contents = file_get_contents($file->getPathname());
            self::assertIsString($contents, "Unable to read {$file->getPathname()}");

            if ($this->importsForbiddenNamespace($contents, $forbiddenNamespacePrefix)) {
                $violations[] = $file->getPathname();
            }
        }

        return $violations;
    }

    private function importsForbiddenNamespace(string $contents, string $forbiddenNamespacePrefix): bool
    {
        return preg_match(
            '/^\s*use\s+' . preg_quote($forbiddenNamespacePrefix, '/') . '/m',
            $contents,
        ) === 1;
    }

    /** @return list<SplFileInfo> */
    private function phpFilesUnder(string $directory): array
    {
        self::assertDirectoryExists($directory);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        );

        $files = [];
        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file;
            }
        }

        return $files;
    }
}
