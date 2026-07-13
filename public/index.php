<?php

declare(strict_types=1);

use Gamad\Core\IdentityRegistry\Application\AtomicIdentityPersister;
use Gamad\Core\IdentityRegistry\Application\Command\RegisterIdentityHandler;
use Gamad\Core\IdentityRegistry\Application\IdentityLifecycleService;
use Gamad\Core\IdentityRegistry\Http\IdentityHttpController;
use Gamad\Core\IdentityRegistry\Http\IdentityRoutes;
use Gamad\Core\IdentityRegistry\Infrastructure\Http\PostgreSqlIdempotencyRepository;
use Gamad\Core\IdentityRegistry\Infrastructure\Persistence\PostgreSqlIdentityIdentifierAuthority;
use Gamad\Core\IdentityRegistry\Infrastructure\Persistence\PostgreSqlIdentityRepository;
use Gamad\Core\IdentityRegistry\Infrastructure\Policy\AllowConfiguredIdentityTypesPolicy;
use Gamad\Core\Shared\Application\DomainEventCollector;
use Gamad\Core\Shared\Application\HealthSummaryQueryService;
use Gamad\Core\Shared\Application\ReplayDeadLetterHandler;
use Gamad\Core\Shared\Http\AdministrativeHttpKernel;
use Gamad\Core\Shared\Http\AdministrativeRoutes;
use Gamad\Core\Shared\Http\AdministrativeRuntimeController;
use Gamad\Core\Shared\Http\OpenApiRequestValidator;
use Gamad\Core\Shared\Http\OpenApiResponseValidator;
use Gamad\Core\Shared\Http\Request;
use Gamad\Core\Shared\Http\ScopeAuthorizationMiddleware;
use Gamad\Core\Shared\Infrastructure\Audit\PostgreSqlAdministrativeAuditRepository;
use Gamad\Core\Shared\Infrastructure\Health\PostgreSqlWorkerStatusRepository;
use Gamad\Core\Shared\Infrastructure\Http\BearerTokenAuthenticationAdapter;
use Gamad\Core\Shared\Infrastructure\Http\CachedRemoteJwksProvider;
use Gamad\Core\Shared\Infrastructure\Http\EnvironmentTokenVerifier;
use Gamad\Core\Shared\Infrastructure\Http\OidcRs256TokenVerifier;
use Gamad\Core\Shared\Infrastructure\Http\PostgreSqlRateLimiter;
use Gamad\Core\Shared\Infrastructure\Metrics\PostgreSqlMetricsCollector;
use Gamad\Core\Shared\Infrastructure\Outbox\PostgreSqlDeadLetterRepository;
use Gamad\Core\Shared\Infrastructure\Outbox\PostgreSqlOutboxDashboardRepository;
use Gamad\Core\Shared\Infrastructure\Outbox\PostgreSqlOutboxRepository;
use Gamad\Core\Shared\Infrastructure\Persistence\PdoTransactionManager;
use Gamad\Core\Shared\Infrastructure\Security\EnvironmentAuthorizationService;
use PDO;
use RuntimeException;

require dirname(__DIR__) . '/vendor/autoload.php';

$dsn = getenv('GAMAD_PG_DSN');
if ($dsn === false || $dsn === '') {
    throw new RuntimeException('Environment variable GAMAD_PG_DSN is required.');
}

$connection = new PDO(
    $dsn,
    getenv('GAMAD_PG_USER') ?: null,
    getenv('GAMAD_PG_PASSWORD') ?: null,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
);

$permissionsJson = getenv('GAMAD_ADMIN_PERMISSIONS_JSON') ?: '{}';
$permissions = json_decode($permissionsJson, true, flags: JSON_THROW_ON_ERROR);
$permissions = is_array($permissions) ? $permissions : [];

$issuer = getenv('GAMAD_OIDC_ISSUER') ?: '';
$audience = getenv('GAMAD_OIDC_AUDIENCE') ?: '';
$jwksUri = getenv('GAMAD_OIDC_JWKS_URI') ?: '';

if ($issuer !== '' || $audience !== '' || $jwksUri !== '') {
    if ($issuer === '' || $audience === '' || $jwksUri === '') {
        throw new RuntimeException('GAMAD_OIDC_ISSUER, GAMAD_OIDC_AUDIENCE and GAMAD_OIDC_JWKS_URI must be configured together.');
    }
    $tokenVerifier = new OidcRs256TokenVerifier(
        jwks: new CachedRemoteJwksProvider(
            jwksUri: $jwksUri,
            cacheFile: getenv('GAMAD_OIDC_JWKS_CACHE_FILE') ?: dirname(__DIR__) . '/var/cache/jwks.json',
            ttlSeconds: (int) (getenv('GAMAD_OIDC_JWKS_TTL_SECONDS') ?: 3600),
        ),
        issuer: $issuer,
        audience: $audience,
        clockSkewSeconds: (int) (getenv('GAMAD_OIDC_CLOCK_SKEW_SECONDS') ?: 60),
    );
} else {
    $tokenVerifier = EnvironmentTokenVerifier::fromJson(getenv('GAMAD_ADMIN_TOKENS_JSON') ?: '{}');
}

$deadLetters = new PostgreSqlDeadLetterRepository($connection);
$administrativeController = new AdministrativeRuntimeController(
    health: new HealthSummaryQueryService(new PostgreSqlWorkerStatusRepository($connection), (int) (getenv('GAMAD_WORKER_STALE_SECONDS') ?: 45)),
    dashboard: new PostgreSqlOutboxDashboardRepository($connection),
    deadLetters: $deadLetters,
    replay: new ReplayDeadLetterHandler($deadLetters, new EnvironmentAuthorizationService($permissions)),
);

$identityRepository = new PostgreSqlIdentityRepository($connection);
$transactionManager = new PdoTransactionManager($connection);
$identityPersister = new AtomicIdentityPersister(
    identities: $identityRepository,
    outbox: new PostgreSqlOutboxRepository($connection),
    events: new DomainEventCollector(),
    transactions: $transactionManager,
);
$registerIdentity = new RegisterIdentityHandler(
    identifiers: new PostgreSqlIdentityIdentifierAuthority($connection),
    policy: new AllowConfiguredIdentityTypesPolicy(),
    persister: $identityPersister,
    metrics: new PostgreSqlMetricsCollector($connection),
);
$identityController = new IdentityHttpController(
    register: $registerIdentity,
    identities: $identityRepository,
    search: $identityRepository,
    lifecycle: new IdentityLifecycleService($identityRepository, $identityPersister),
    idempotency: new PostgreSqlIdempotencyRepository($connection),
    transactions: $transactionManager,
);

$routes = array_merge(
    AdministrativeRoutes::forController($administrativeController),
    IdentityRoutes::forController($identityController),
);
$kernel = new AdministrativeHttpKernel(
    validator: new OpenApiRequestValidator($routes),
    responseValidator: new OpenApiResponseValidator(),
    authentication: new BearerTokenAuthenticationAdapter($tokenVerifier),
    authorization: new ScopeAuthorizationMiddleware(),
    rateLimiter: new PostgreSqlRateLimiter($connection),
    audit: new PostgreSqlAdministrativeAuditRepository($connection),
    rateLimit: (int) (getenv('GAMAD_ADMIN_RATE_LIMIT') ?: 120),
    rateWindowSeconds: (int) (getenv('GAMAD_ADMIN_RATE_WINDOW_SECONDS') ?: 60),
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
