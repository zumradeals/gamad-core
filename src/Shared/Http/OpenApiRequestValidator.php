<?php

declare(strict_types=1);

namespace Gamad\Core\Shared\Http;

use InvalidArgumentException;

final readonly class OpenApiRequestValidator
{
    /** @param list<RouteDefinition> $routes */
    public function __construct(private array $routes)
    {
    }

    /** @return array{route: RouteDefinition, request: Request} */
    public function validate(Request $request): array
    {
        foreach ($this->routes as $route) {
            $parameters = $route->match($request->method, $request->path);
            if ($parameters === null) {
                continue;
            }

            if (isset($parameters['messageId']) && preg_match(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
                $parameters['messageId'],
            ) !== 1) {
                throw new InvalidArgumentException('Path parameter messageId must be a valid UUID.');
            }

            foreach (['limit', 'offset'] as $name) {
                if (!isset($request->query[$name])) {
                    continue;
                }

                $value = filter_var($request->query[$name], FILTER_VALIDATE_INT);
                if ($value === false || ($name === 'limit' && ($value < 1 || $value > 500)) || ($name === 'offset' && $value < 0)) {
                    throw new InvalidArgumentException(sprintf('Invalid query parameter %s.', $name));
                }
            }

            return ['route' => $route, 'request' => $request->withPathParameters($parameters)];
        }

        throw new InvalidArgumentException('No OpenAPI operation matches this request.');
    }
}
