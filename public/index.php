<?php

declare(strict_types=1);

use Gamad\Core\Shared\Application\HealthSummaryQueryService;
use Gamad\Core\Shared\Application\ReplayDeadLetterHandler;
use Gamad\Core\Shared\Http\AdministrativeHttpKernel;
use Gamad\Core\Shared\Http\AdministrativeRoutes;
use Gamad\Core\Shared\Http\AdministrativeRuntimeController;
use Gamad\Core\Shared\Http\OpenApiRequestValidator;
use Gamad\Core\Shared\Http\Request;
use Gamad\Core\Shared\Http\ScopeAuthorizationMiddleware;
use Gamad\Core\Shared\Infrastructure\Audit\PostgreSqlAdministrativeAuditRepository;
use Gamad\Core\Shared\Infrastructure\Health\PostgreSqlWorkerStatusRepository;
use Gamad\Core\Shared\Infrastructure\Http\EnvironmentBearerAuthenticationAdapter;
use Gamad\Core\Shared\Infrastructure\Outbox\PostgreSqlDeadLetterRepository;
use Gamad\Core\Shared\Infrastructure\Outbox\PostgreSqlOutboxDashboardRepository;
use Gamad\Core\Shared\Infrastructure\Security\EnvironmentAuthorizationService;
use PDO;
use RuntimeException;

require dirname(__DIR__) . '/vendor/autoload.php';

$dsn = getenv('GAMAD_PG_DSN');
if ($dsn === false || $dsn === '') {
    throw new RuntimeException('Environment variable GAMAD_PG_DSN is required.');
}

$tokensJson = getenv('GAMAD_ADMIN_TOKENS_JSON') ?: '{}';
$tokens = json_decode($tokensJson, true, flags: JSON_THROW_ON_ERROR);
$tokens = is_array($tokens) ? $tokens : [];
$permissionsByActor = [];
foreach ($tokens as $token) {
    if (is_array($token) && isset($token['actor_id'], $token['scopes']) && is_array($token['scopes'])) {
        $permissionsByActor[(string) $token['actor_id']] = array_values(array_map('strval', $token['scopes']));
    }
}

$connection = new PDO(
    $dsn,
    getenv('GAMAD_PG_USER') ?: null,
    getenv('GAMAD_PG_PASSWORD') ?: null,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
);
$deadLetters = new PostgreSqlDeadLetterRepository($connection);
$controller = new AdministrativeRuntimeController(
    health: new HealthSummaryQueryService(
        new PostgreSqlWorkerStatusRepository($connection),
        (int) (getenv('GAMAD_WORKER_STALE_SECONDS') ?: 45),
    ),
    dashboard: new PostgreSqlOutboxDashboardRepository($connection),
    deadLetters: $deadLetters,
    replay: new ReplayDeadLetterHandler(
        $deadLetters,
        new EnvironmentAuthorizationService($permissionsByActor),
    ),
);
$routes = AdministrativeRoutes::forController($controller);
$kernel = new AdministrativeHttpKernel(
    validator: new OpenApiRequestValidator($routes),
    authentication: EnvironmentBearerAuthenticationAdapter::fromJson($tokensJson),
    authorization: new ScopeAuthorizationMiddleware(),
    audit: new PostgreSqlAdministrativeAuditRepository($connection),
);

$headers = [];
foreach (getallheaders() ?: [] as $name => $value) {
    $headers[(string) $name] = (string) $value;
}
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$request = new Request(
    method: strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
    path: $path,
    headers: $headers,
    query: array_map('strval', $_GET),
    body: file_get_contents('php://input') ?: '',
);
$response = $kernel->handle($request);

http_response_code($response->status);
foreach ($response->headers as $name => $value) {
    header($name . ': ' . $value);
}
echo $response->body;
