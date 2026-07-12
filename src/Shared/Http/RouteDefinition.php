<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Http;

use Closure;

final readonly class RouteDefinition
{
    /** @param list<string> $requiredScopes */
    public function __construct(
        public string $method,
        public string $template,
        public array $requiredScopes,
        public Closure $handler,
        public string $operationId,
    ) {
    }

    /** @return array<string, string>|null */
    public function match(string $method, string $path): ?array
    {
        if (strcasecmp($this->method, $method) !== 0) {
            return null;
        }

        $pattern = preg_replace_callback(
            '/\{([A-Za-z][A-Za-z0-9_]*)\}/',
            static fn (array $match): string => '(?P<' . $match[1] . '>[^/]+)',
            $this->template,
        );

        if ($pattern === null || preg_match('#^' . $pattern . '$#', $path, $matches) !== 1) {
            return null;
        }

        $parameters = [];
        foreach ($matches as $key => $value) {
            if (is_string($key)) {
                $parameters[$key] = rawurldecode((string) $value);
            }
        }

        return $parameters;
    }
}
