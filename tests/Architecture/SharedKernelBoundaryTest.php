<?php

declare(strict_types=1);

namespace Gamad\Core\Tests\Architecture;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Fitness function for ADR-0013 — Shared is a technical kernel and must never
 * depend on a bounded context such as IdentityRegistry.
 */
final class SharedKernelBoundaryTest extends TestCase
{
    private const string SHARED_DIRECTORY = __DIR__ . '/../../src/Shared';
    private const string FORBIDDEN_NAMESPACE_PREFIX = 'Gamad\\Core\\IdentityRegistry\\';

    public function test_shared_never_imports_identity_registry(): void
    {
        $violations = [];

        foreach ($this->phpFilesUnderShared() as $file) {
            $contents = file_get_contents($file->getPathname());
            self::assertIsString($contents, "Unable to read {$file->getPathname()}");

            if ($this->importsForbiddenNamespace($contents)) {
                $violations[] = $file->getPathname();
            }
        }

        self::assertSame(
            [],
            $violations,
            "The following files under src/Shared import Gamad\\Core\\IdentityRegistry\\*, "
            . "which violates ADR-0013 (Shared kernel boundary):\n"
            . implode("\n", $violations),
        );
    }

    private function importsForbiddenNamespace(string $contents): bool
    {
        return preg_match(
            '/^\s*use\s+' . preg_quote(self::FORBIDDEN_NAMESPACE_PREFIX, '/') . '/m',
            $contents,
        ) === 1;
    }

    /** @return list<SplFileInfo> */
    private function phpFilesUnderShared(): array
    {
        self::assertDirectoryExists(self::SHARED_DIRECTORY);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(self::SHARED_DIRECTORY, FilesystemIterator::SKIP_DOTS),
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
