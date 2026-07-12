<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Infrastructure\Observability;

use DateTimeImmutable;
use Gamad\Core\Shared\Observability\StructuredLogger;
use JsonException;

final readonly class JsonLineLogger implements StructuredLogger
{
    /** @param resource $stream */
    public function __construct(private mixed $stream)
    {
    }

    public function info(string $message, array $context = []): void
    {
        $this->write('info', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('error', $message, $context);
    }

    /** @param array<string, mixed> $context */
    private function write(string $level, string $message, array $context): void
    {
        try {
            $line = json_encode([
                'timestamp' => (new DateTimeImmutable())->format(DATE_ATOM),
                'level' => $level,
                'message' => $message,
                'context' => $context,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException) {
            $line = '{"level":"error","message":"structured_log_encoding_failed"}';
        }

        fwrite($this->stream, $line . PHP_EOL);
    }
}
