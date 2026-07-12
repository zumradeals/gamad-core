<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Infrastructure\Http;

use Gamad\Core\Shared\Http\JwksProvider;
use RuntimeException;

final readonly class CachedRemoteJwksProvider implements JwksProvider
{
    public function __construct(
        private string $jwksUri,
        private string $cacheFile,
        private int $ttlSeconds = 3600,
        private int $timeoutSeconds = 5,
    ) {
    }

    public function keys(): array
    {
        $payload = $this->readFreshCache() ?? $this->fetchAndCache();
        $decoded = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
        $keys = $decoded['keys'] ?? null;

        if (!is_array($keys)) {
            throw new RuntimeException('JWKS document does not contain a keys array.');
        }

        $indexed = [];
        foreach ($keys as $key) {
            if (is_array($key) && isset($key['kid'])) {
                $indexed[(string) $key['kid']] = $key;
            }
        }

        return $indexed;
    }

    private function readFreshCache(): ?string
    {
        if (!is_file($this->cacheFile)) {
            return null;
        }

        $modifiedAt = filemtime($this->cacheFile);
        if ($modifiedAt === false || (time() - $modifiedAt) >= $this->ttlSeconds) {
            return null;
        }

        $contents = file_get_contents($this->cacheFile);

        return $contents === false ? null : $contents;
    }

    private function fetchAndCache(): string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $this->timeoutSeconds,
                'header' => "Accept: application/json\r\n",
                'ignore_errors' => false,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);
        $payload = @file_get_contents($this->jwksUri, false, $context);

        if ($payload === false) {
            if (is_file($this->cacheFile)) {
                $stale = file_get_contents($this->cacheFile);
                if ($stale !== false) {
                    return $stale;
                }
            }

            throw new RuntimeException('Unable to retrieve JWKS document.');
        }

        $directory = dirname($this->cacheFile);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create JWKS cache directory.');
        }

        $temporary = $this->cacheFile . '.' . bin2hex(random_bytes(4)) . '.tmp';
        file_put_contents($temporary, $payload, LOCK_EX);
        chmod($temporary, 0600);
        rename($temporary, $this->cacheFile);

        return $payload;
    }
}
