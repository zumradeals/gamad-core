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
use Gamad\Core\IdentityRegistry\Infrastructure\Policy\AllowConfiguredRealmPolicy;
use Gamad\Core\AccessControl\Application\AtomicRoleAssignmentPersister;
use Gamad\Core\AccessControl\Application\AtomicRolePersister;
use Gamad\Core\AccessControl\Application\Command\AddPermissionToRoleHandler;
use Gamad\Core\AccessControl\Application\Command\AssignRoleHandler;
use Gamad\Core\AccessControl\Application\Command\CreatePermissionHandler;
use Gamad\Core\AccessControl\Application\Command\CreateRoleHandler;
use Gamad\Core\AccessControl\Application\Command\EvaluateAccessHandler;
use Gamad\Core\AccessControl\Application\Command\RevokeRoleHandler;
use Gamad\Core\AccessControl\Domain\AccessControlEngine;
use Gamad\Core\AccessControl\Http\AccessControlHttpController;
use Gamad\Core\AccessControl\Http\AccessControlHttpKernel;
use Gamad\Core\AccessControl\Http\AccessControlResponseValidator;
use Gamad\Core\AccessControl\Http\AccessControlRoutes;
use Gamad\Core\AccessControl\Infrastructure\Outbox\PostgreSqlAccessDecisionsOutboxRepository;
use Gamad\Core\AccessControl\Infrastructure\Persistence\PostgreSqlAccessControlLookup;
use Gamad\Core\AccessControl\Infrastructure\Persistence\PostgreSqlPermissionRepository;
use Gamad\Core\AccessControl\Infrastructure\Persistence\PostgreSqlRoleAssignmentRepository;
use Gamad\Core\AccessControl\Infrastructure\Persistence\PostgreSqlRoleRepository;
use Gamad\Core\OrganizationsAndMemberships\Application\AtomicMembershipPersister;
use Gamad\Core\OrganizationsAndMemberships\Application\AtomicOrganizationPersister;
use Gamad\Core\OrganizationsAndMemberships\Application\Command\CreateDepartmentHandler;
use Gamad\Core\OrganizationsAndMemberships\Application\Command\CreateMembershipHandler;
use Gamad\Core\OrganizationsAndMemberships\Application\Command\CreateOrganizationHandler;
use Gamad\Core\OrganizationsAndMemberships\Application\Command\EndMembershipHandler;
use Gamad\Core\OrganizationsAndMemberships\Application\Command\ResumeMembershipHandler;
use Gamad\Core\OrganizationsAndMemberships\Application\Command\SuspendMembershipHandler;
use Gamad\Core\OrganizationsAndMemberships\Http\OrganizationsAndMembershipsHttpController;
use Gamad\Core\OrganizationsAndMemberships\Http\OrganizationsAndMembershipsHttpKernel;
use Gamad\Core\OrganizationsAndMemberships\Http\OrganizationsAndMembershipsResponseValidator;
use Gamad\Core\OrganizationsAndMemberships\Http\OrganizationsAndMembershipsRoutes;
use Gamad\Core\OrganizationsAndMemberships\Infrastructure\IdentityRegistry\PostgreSqlIdentityLookup as OrganizationsIdentityLookup;
use Gamad\Core\OrganizationsAndMemberships\Infrastructure\Persistence\PostgreSqlMembershipRepository;
use Gamad\Core\OrganizationsAndMemberships\Infrastructure\Persistence\PostgreSqlOrganizationRepository;
use Gamad\Core\OrganizationsAndMemberships\Infrastructure\PersonsAndAccounts\PostgreSqlPersonLookup;
use Gamad\Core\PersonsAndAccounts\Application\AtomicPersonPersister;
use Gamad\Core\PersonsAndAccounts\Application\AtomicSessionPersister;
use Gamad\Core\PersonsAndAccounts\Application\AtomicUserAccountPersister;
use Gamad\Core\PersonsAndAccounts\Application\Command\AuthenticateHandler;
use Gamad\Core\PersonsAndAccounts\Application\Command\RegisterPersonHandler;
use Gamad\Core\PersonsAndAccounts\Application\Command\RegisterUserAccountHandler;
use Gamad\Core\PersonsAndAccounts\Application\Command\RevokeSessionHandler;
use Gamad\Core\PersonsAndAccounts\Application\Command\SetPasswordHandler;
use Gamad\Core\PersonsAndAccounts\Http\PersonsAndAccountsHttpController;
use Gamad\Core\PersonsAndAccounts\Http\PersonsAndAccountsHttpKernel;
use Gamad\Core\PersonsAndAccounts\Http\PersonsAndAccountsResponseValidator;
use Gamad\Core\PersonsAndAccounts\Http\PersonsAndAccountsRoutes;
use Gamad\Core\PersonsAndAccounts\Infrastructure\Http\SessionTokenAuthenticator;
use Gamad\Core\PersonsAndAccounts\Infrastructure\IdentityRegistry\PostgreSqlIdentityLookup;
use Gamad\Core\PersonsAndAccounts\Infrastructure\Persistence\PostgreSqlPersonRepository;
use Gamad\Core\PersonsAndAccounts\Infrastructure\Persistence\PostgreSqlSessionRepository;
use Gamad\Core\PersonsAndAccounts\Infrastructure\Persistence\PostgreSqlUserAccountRepository;
use Gamad\Core\Shared\Application\DomainEventCollector;
use Gamad\Core\Shared\Application\HealthSummaryQueryService;
use Gamad\Core\Shared\Application\ReplayDeadLetterHandler;
use Gamad\Core\Shared\Contract\AccessControlGateway;
use Gamad\Core\Shared\Http\AdministrativeHttpKernel;
use Gamad\Core\Shared\Http\AdministrativeRoutes;
use Gamad\Core\Shared\Http\AdministrativeRuntimeController;
use Gamad\Core\Shared\Http\OpenApiRequestValidator;
use Gamad\Core\Shared\Http\OpenApiResponseValidator;
use Gamad\Core\Shared\Http\Request;
use Gamad\Core\Shared\Http\ScopeAuthorizationMiddleware;
use Gamad\Core\Shared\Infrastructure\AccessControl\PermissiveAccessControlGateway;
use Gamad\Core\Shared\Infrastructure\Audit\PostgreSqlAdministrativeAuditRepository;
use Gamad\Core\Shared\Infrastructure\Audit\PostgreSqlAuditChainVerifier;
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

$coreRealm = getenv('GAMAD_CORE_REALM');
if ($coreRealm === false || $coreRealm === '') {
    throw new RuntimeException('Environment variable GAMAD_CORE_REALM is required.');
}
$realmPolicy = new AllowConfiguredRealmPolicy($coreRealm);

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
    auditVerifier: new PostgreSqlAuditChainVerifier($connection),
);

// ADR-0021 — provisional gateway, active until DIRECTIVE-007 Task 10 swaps
// it for RbacAccessControlGateway once the real engine is validated.
$accessControlGateway = new PermissiveAccessControlGateway();

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
    realm: $realmPolicy,
    accessControl: $accessControlGateway,
);
$identityController = new IdentityHttpController(
    register: $registerIdentity,
    identities: $identityRepository,
    search: $identityRepository,
    lifecycle: new IdentityLifecycleService($identityRepository, $identityPersister, $accessControlGateway),
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

// Persons and User Accounts (DIRECTIVE-003) — a distinct HTTP surface, on
// purpose: session-token authentication, never the ADR-0011 administrative
// bootstrap (Task 6).
$personRepository = new PostgreSqlPersonRepository($connection);
$userAccountRepository = new PostgreSqlUserAccountRepository($connection);
$sessionRepository = new PostgreSqlSessionRepository($connection);
$personsAndAccountsOutbox = new PostgreSqlOutboxRepository($connection);
$personsAndAccountsEvents = new DomainEventCollector();
$personsAndAccountsController = new PersonsAndAccountsHttpController(
    registerPerson: new RegisterPersonHandler(
        identities: new PostgreSqlIdentityLookup($connection),
        persons: $personRepository,
        persister: new AtomicPersonPersister($personRepository, $personsAndAccountsOutbox, $personsAndAccountsEvents, $transactionManager),
        accessControl: $accessControlGateway,
    ),
    persons: $personRepository,
    registerUserAccount: new RegisterUserAccountHandler(
        persons: $personRepository,
        accounts: $userAccountRepository,
        persister: new AtomicUserAccountPersister($userAccountRepository, $personsAndAccountsOutbox, $personsAndAccountsEvents, $transactionManager),
        accessControl: $accessControlGateway,
    ),
    setPassword: new SetPasswordHandler(
        accounts: $userAccountRepository,
        persister: new AtomicUserAccountPersister($userAccountRepository, $personsAndAccountsOutbox, $personsAndAccountsEvents, $transactionManager),
    ),
    authenticate: new AuthenticateHandler(
        accounts: $userAccountRepository,
        persister: new AtomicSessionPersister($sessionRepository, $personsAndAccountsOutbox, $personsAndAccountsEvents, $transactionManager),
    ),
    revokeSession: new RevokeSessionHandler(
        sessions: $sessionRepository,
        persister: new AtomicSessionPersister($sessionRepository, $personsAndAccountsOutbox, $personsAndAccountsEvents, $transactionManager),
        accessControl: $accessControlGateway,
    ),
);
$personsAndAccountsRoutes = PersonsAndAccountsRoutes::forController($personsAndAccountsController);
$personsAndAccountsKernel = new PersonsAndAccountsHttpKernel(
    validator: new OpenApiRequestValidator($personsAndAccountsRoutes),
    responseValidator: new PersonsAndAccountsResponseValidator(),
    sessionAuthentication: new SessionTokenAuthenticator($sessionRepository),
    rateLimiter: new PostgreSqlRateLimiter($connection),
    audit: new PostgreSqlAdministrativeAuditRepository($connection),
    rateLimit: (int) (getenv('GAMAD_ADMIN_RATE_LIMIT') ?: 120),
    rateWindowSeconds: (int) (getenv('GAMAD_ADMIN_RATE_WINDOW_SECONDS') ?: 60),
);

// Organizations and Memberships (DIRECTIVE-006) — a distinct HTTP surface
// from both the administrative bootstrap and Persons and User Accounts,
// though it authenticates with the same session-token mechanism as the
// latter (Task 6). Reuses the shared SessionTokenAuthenticator instance —
// only the composition root is allowed to know both contexts.
$organizationRepository = new PostgreSqlOrganizationRepository($connection);
$membershipRepository = new PostgreSqlMembershipRepository($connection);
$organizationsAndMembershipsOutbox = new PostgreSqlOutboxRepository($connection);
$organizationsAndMembershipsEvents = new DomainEventCollector();
$organizationsAndMembershipsController = new OrganizationsAndMembershipsHttpController(
    createOrganization: new CreateOrganizationHandler(
        identities: new OrganizationsIdentityLookup($connection),
        organizations: $organizationRepository,
        persister: new AtomicOrganizationPersister($organizationRepository, $organizationsAndMembershipsOutbox, $organizationsAndMembershipsEvents, $transactionManager),
        accessControl: $accessControlGateway,
    ),
    organizations: $organizationRepository,
    createDepartment: new CreateDepartmentHandler(
        organizations: $organizationRepository,
        persister: new AtomicOrganizationPersister($organizationRepository, $organizationsAndMembershipsOutbox, $organizationsAndMembershipsEvents, $transactionManager),
        accessControl: $accessControlGateway,
    ),
    createMembership: new CreateMembershipHandler(
        persons: new PostgreSqlPersonLookup($connection),
        organizations: $organizationRepository,
        memberships: $membershipRepository,
        persister: new AtomicMembershipPersister($membershipRepository, $organizationsAndMembershipsOutbox, $organizationsAndMembershipsEvents, $transactionManager),
        accessControl: $accessControlGateway,
    ),
    memberships: $membershipRepository,
    suspendMembership: new SuspendMembershipHandler(
        memberships: $membershipRepository,
        persister: new AtomicMembershipPersister($membershipRepository, $organizationsAndMembershipsOutbox, $organizationsAndMembershipsEvents, $transactionManager),
        accessControl: $accessControlGateway,
    ),
    resumeMembership: new ResumeMembershipHandler(
        memberships: $membershipRepository,
        persister: new AtomicMembershipPersister($membershipRepository, $organizationsAndMembershipsOutbox, $organizationsAndMembershipsEvents, $transactionManager),
        accessControl: $accessControlGateway,
    ),
    endMembership: new EndMembershipHandler(
        memberships: $membershipRepository,
        persister: new AtomicMembershipPersister($membershipRepository, $organizationsAndMembershipsOutbox, $organizationsAndMembershipsEvents, $transactionManager),
        accessControl: $accessControlGateway,
    ),
);
$organizationsAndMembershipsRoutes = OrganizationsAndMembershipsRoutes::forController($organizationsAndMembershipsController);
$organizationsAndMembershipsKernel = new OrganizationsAndMembershipsHttpKernel(
    validator: new OpenApiRequestValidator($organizationsAndMembershipsRoutes),
    responseValidator: new OrganizationsAndMembershipsResponseValidator(),
    sessionAuthentication: new SessionTokenAuthenticator($sessionRepository),
    rateLimiter: new PostgreSqlRateLimiter($connection),
    audit: new PostgreSqlAdministrativeAuditRepository($connection),
    rateLimit: (int) (getenv('GAMAD_ADMIN_RATE_LIMIT') ?: 120),
    rateWindowSeconds: (int) (getenv('GAMAD_ADMIN_RATE_WINDOW_SECONDS') ?: 60),
);

// Access Control (DIRECTIVE-007) — a distinct HTTP surface, authenticating
// with the same session-token mechanism as Persons and User Accounts and
// Organizations and Memberships (Task 7). $accessControlGateway
// (PermissiveAccessControlGateway, wired above) is what the three
// existing contexts call; this composition wires the real engine's own
// handlers directly, since AccessControl is the engine's home context.
$permissionRepository = new PostgreSqlPermissionRepository($connection);
$roleRepository = new PostgreSqlRoleRepository($connection);
$roleAssignmentRepository = new PostgreSqlRoleAssignmentRepository($connection);
$accessDecisionsOutbox = new PostgreSqlAccessDecisionsOutboxRepository($connection);
$accessControlOutbox = new PostgreSqlOutboxRepository($connection);
$accessControlEvents = new DomainEventCollector();
$evaluateAccessHandler = new EvaluateAccessHandler(
    assignments: $roleAssignmentRepository,
    roles: $roleRepository,
    permissions: $permissionRepository,
    engine: new AccessControlEngine(),
    accessDecisionsOutbox: $accessDecisionsOutbox,
);
$realmRootOrganizationId = sprintf('GAM-%s-ORG-000001', $coreRealm);
$accessControlLookup = new PostgreSqlAccessControlLookup($connection);
$accessControlController = new AccessControlHttpController(
    createPermission: new CreatePermissionHandler($permissionRepository),
    permissions: $permissionRepository,
    createRole: new CreateRoleHandler(
        roles: $roleRepository,
        persister: new AtomicRolePersister($roleRepository, $accessControlOutbox, $accessControlEvents, $transactionManager),
    ),
    roles: $roleRepository,
    addPermissionToRole: new AddPermissionToRoleHandler(
        roles: $roleRepository,
        permissions: $permissionRepository,
        persister: new AtomicRolePersister($roleRepository, $accessControlOutbox, $accessControlEvents, $transactionManager),
    ),
    assignRole: new AssignRoleHandler(
        roles: $roleRepository,
        assignments: $roleAssignmentRepository,
        lookup: $accessControlLookup,
        evaluateAccess: $evaluateAccessHandler,
        persister: new AtomicRoleAssignmentPersister($roleAssignmentRepository, $accessControlOutbox, $accessControlEvents, $transactionManager),
    ),
    revokeRole: new RevokeRoleHandler(
        assignments: $roleAssignmentRepository,
        persister: new AtomicRoleAssignmentPersister($roleAssignmentRepository, $accessControlOutbox, $accessControlEvents, $transactionManager),
    ),
    evaluateAccess: $evaluateAccessHandler,
    lookup: $accessControlLookup,
    realmRootOrganizationId: $realmRootOrganizationId,
);
$accessControlRoutes = AccessControlRoutes::forController($accessControlController);
$accessControlKernel = new AccessControlHttpKernel(
    validator: new OpenApiRequestValidator($accessControlRoutes),
    responseValidator: new AccessControlResponseValidator(),
    sessionAuthentication: new SessionTokenAuthenticator($sessionRepository),
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
$matchesPersonsAndAccounts = false;
foreach ($personsAndAccountsRoutes as $route) {
    if ($route->match($request->method, $request->path) !== null) {
        $matchesPersonsAndAccounts = true;
        break;
    }
}
$matchesOrganizationsAndMemberships = false;
foreach ($organizationsAndMembershipsRoutes as $route) {
    if ($route->match($request->method, $request->path) !== null) {
        $matchesOrganizationsAndMemberships = true;
        break;
    }
}
$matchesAccessControl = false;
foreach ($accessControlRoutes as $route) {
    if ($route->match($request->method, $request->path) !== null) {
        $matchesAccessControl = true;
        break;
    }
}
$response = match (true) {
    $matchesPersonsAndAccounts => $personsAndAccountsKernel->handle($request),
    $matchesOrganizationsAndMemberships => $organizationsAndMembershipsKernel->handle($request),
    $matchesAccessControl => $accessControlKernel->handle($request),
    default => $kernel->handle($request),
};

http_response_code($response->status);
foreach ($response->headers as $name => $value) {
    header($name . ': ' . $value);
}
echo $response->body;
