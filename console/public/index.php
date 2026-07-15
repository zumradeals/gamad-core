<?php

declare(strict_types=1);

// When served by PHP's built-in dev server, let it serve real static files
// (CSS) directly instead of routing them through this front controller.
// No-op under Apache/FPM in production (different SAPI).
if (PHP_SAPI === 'cli-server') {
    $requested = __DIR__ . parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if ($requested !== __DIR__ . '/' && is_file($requested)) {
        return false;
    }
}

require dirname(__DIR__) . '/lib/Session.php';
require dirname(__DIR__) . '/lib/CoreApiResponse.php';
require dirname(__DIR__) . '/lib/CoreApiClient.php';

use Gamad\Console\Lib\CoreApiClient;
use Gamad\Console\Lib\Session;

Session::start();

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function apiClient(): CoreApiClient
{
    return new CoreApiClient();
}

function forbiddenMessage(): string
{
    return "Le token utilisé n'a pas le droit d'effectuer cette action.";
}

/**
 * ADR-0019 — each screen declares explicitly which credential context it
 * needs and is redirected to the matching login screen if absent. A 401
 * encountered later, mid-call, is handled by CoreApiClient::callAsAdmin()
 * itself (never here) so the two guards can never cross.
 */
function requireAdminLogin(): CoreApiClient
{
    if (!Session::isAdminAuthenticated()) {
        redirect('/login');
    }

    return apiClient();
}

function requirePersonLogin(): CoreApiClient
{
    if (!Session::isPersonAuthenticated()) {
        redirect('/login-operateur');
    }

    return apiClient();
}

/** @param array<string, mixed> $vars */
function render(string $template, array $vars = [], string $pageTitle = "GAMAD Core — Console d'Exploitation"): void
{
    $adminAuthenticated = Session::isAdminAuthenticated();
    $personAuthenticated = Session::isPersonAuthenticated();
    extract($vars);
    require dirname(__DIR__) . '/templates/_header.php';
    require dirname(__DIR__) . '/templates/' . $template . '.php';
    require dirname(__DIR__) . '/templates/_footer.php';
}

$path = rtrim((string) (parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/'), '/');
if ($path === '') {
    $path = '/';
}
$method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');

/**
 * Minimal regex router. No framework, matching ADR-0016's "console is a pure
 * HTTP client, no business logic" spirit — this only dispatches to a handler.
 */
$routes = [
    ['GET', '#^/login$#', 'handle_login_form'],
    ['POST', '#^/login$#', 'handle_login_submit'],
    ['GET', '#^/login-operateur$#', 'handle_operator_login_form'],
    ['POST', '#^/login-operateur$#', 'handle_operator_login_submit'],
    ['GET', '#^/logout$#', 'handle_logout_form'],
    ['POST', '#^/logout/admin$#', 'handle_logout_admin'],
    ['POST', '#^/logout/operateur$#', 'handle_logout_operator'],
    ['POST', '#^/logout/all$#', 'handle_logout_all'],
    ['GET', '#^/$#', 'handle_dashboard'],
    ['GET', '#^/identities$#', 'handle_identities_list'],
    ['GET', '#^/identities/new$#', 'handle_identity_register_form'],
    ['POST', '#^/identities$#', 'handle_identity_register_submit'],
    ['GET', '#^/identities/([A-Z0-9-]+)$#', 'handle_identity_detail'],
    ['GET', '#^/identities/([A-Z0-9-]+)/transition/(activate|suspend|archive|revoke)$#', 'handle_identity_transition_confirm'],
    ['POST', '#^/identities/([A-Z0-9-]+)/transition/(activate|suspend|archive|revoke)$#', 'handle_identity_transition_submit'],
    ['GET', '#^/personnes$#', 'handle_persons_screen'],
    ['POST', '#^/personnes$#', 'handle_person_register_submit'],
    ['POST', '#^/personnes/([A-Z0-9-]+)/account$#', 'handle_person_account_create_submit'],
    ['GET', '#^/organisations$#', 'handle_organizations_screen'],
    ['POST', '#^/organisations$#', 'handle_organization_create_submit'],
    ['GET', '#^/organisations/([A-Z0-9-]+)$#', 'handle_organization_detail'],
    ['POST', '#^/organisations/([A-Z0-9-]+)/departments$#', 'handle_department_create_submit'],
    ['GET', '#^/organisations/([A-Z0-9-]+)/membres$#', 'handle_organization_memberships_screen'],
    ['POST', '#^/organisations/([A-Z0-9-]+)/membres$#', 'handle_membership_create_submit'],
    ['POST', '#^/memberships/([0-9a-fA-F-]+)/end$#', 'handle_membership_end_submit'],
    ['GET', '#^/dead-letters$#', 'handle_dead_letters_list'],
    ['GET', '#^/dead-letters/([0-9a-fA-F-]+)$#', 'handle_dead_letter_detail'],
    ['GET', '#^/dead-letters/([0-9a-fA-F-]+)/replay$#', 'handle_dead_letter_replay_confirm'],
    ['POST', '#^/dead-letters/([0-9a-fA-F-]+)/replay$#', 'handle_dead_letter_replay_submit'],
];

foreach ($routes as [$routeMethod, $pattern, $handler]) {
    if ($routeMethod !== $method) {
        continue;
    }
    if (preg_match($pattern, $path, $matches) === 1) {
        array_shift($matches);
        $handler(...$matches);
        exit;
    }
}

http_response_code(404);
render('_not_found');
exit;

// --- Handlers -------------------------------------------------------------

function handle_login_form(): void
{
    if (Session::isAdminAuthenticated()) {
        redirect('/');
    }
    render('login', [], "Connexion admin — Console d'Exploitation");
}

function handle_login_submit(): void
{
    $token = trim((string) ($_POST['token'] ?? ''));
    if ($token === '') {
        Session::flash('error', 'Le token ne peut pas être vide.');
        redirect('/login');
    }

    $client = apiClient();
    $response = $client->get('/admin/runtime/health', [], $token);

    if ($response->unauthorized()) {
        Session::flash('error', 'Token invalide ou expiré.');
        redirect('/login');
    }
    if (!$response->ok() && !$response->forbidden()) {
        Session::flash('error', 'Le Core est injoignable pour le moment. Réessayez.');
        redirect('/login');
    }

    // A 403 here still proves the token is authenticated, just scoped
    // narrower than health — accept it, later screens will guard per-route.
    Session::setAdminToken($token);
    redirect('/');
}

function handle_operator_login_form(): void
{
    if (Session::isPersonAuthenticated()) {
        redirect('/personnes');
    }
    render('login-operateur', [], "Connexion opérateur — Console d'Exploitation");
}

function handle_operator_login_submit(): void
{
    $personId = trim((string) ($_POST['person_id'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    if ($personId === '' || $password === '') {
        Session::flash('error', 'Identifiant et mot de passe requis.');
        redirect('/login-operateur');
    }

    $client = apiClient();
    $response = $client->post('/auth/login', ['person_id' => $personId, 'password' => $password]);

    if ($response->unauthorized()) {
        // ADR-0018: never reveal whether the account exists — a generic
        // message covers both "no such person" and "wrong password".
        Session::flash('error', 'Identifiant ou mot de passe incorrect.');
        redirect('/login-operateur');
    }
    if (!$response->ok()) {
        Session::flash('error', 'Le Core est injoignable pour le moment. Réessayez.');
        redirect('/login-operateur');
    }

    Session::setPersonToken((string) $response->data['token']);
    redirect('/personnes');
}

function handle_logout_form(): void
{
    render('logout', [
        'csrf' => Session::csrfToken(),
    ], "Déconnexion — Console d'Exploitation");
}

function handle_logout_admin(): void
{
    if (Session::checkCsrf($_POST['csrf'] ?? null)) {
        Session::destroyAdmin();
        Session::flash('success', 'Déconnexion admin effectuée.');
    }
    redirect('/logout');
}

function handle_logout_operator(): void
{
    if (Session::checkCsrf($_POST['csrf'] ?? null)) {
        Session::destroyPerson();
        Session::flash('success', 'Déconnexion opérateur effectuée.');
    }
    redirect('/logout');
}

function handle_logout_all(): void
{
    if (Session::checkCsrf($_POST['csrf'] ?? null)) {
        Session::destroyAll();
        Session::flash('success', 'Déconnexion complète effectuée.');
    }
    redirect('/logout');
}

function handle_dashboard(): void
{
    $client = requireAdminLogin();

    $health = $client->callAsAdmin('GET', '/admin/runtime/health');
    $outbox = $client->callAsAdmin('GET', '/admin/runtime/outbox');
    $audit = $client->callAsAdmin('GET', '/admin/runtime/audit/verify');

    render('dashboard', [
        'health' => $health,
        'outbox' => $outbox,
        'audit' => $audit,
    ], "Tableau de bord — Console d'Exploitation");
}

function handle_identities_list(): void
{
    $client = requireAdminLogin();

    $filters = [
        'type' => trim((string) ($_GET['type'] ?? '')),
        'status' => trim((string) ($_GET['status'] ?? '')),
        'cursor' => trim((string) ($_GET['cursor'] ?? '')),
    ];
    $query = array_filter($filters, static fn (string $v): bool => $v !== '');

    $response = $client->callAsAdmin('GET', '/identities', $query);

    if ($response->forbidden()) {
        Session::flash('error', forbiddenMessage());
        render('identities_list', ['items' => [], 'nextCursor' => null, 'filters' => $filters], 'Identités — Console d\'Exploitation');
        return;
    }

    render('identities_list', [
        'items' => $response->data['items'] ?? [],
        'nextCursor' => $response->data['next_cursor'] ?? null,
        'filters' => $filters,
    ], "Identités — Console d'Exploitation");
}

function handle_identity_register_form(): void
{
    requireAdminLogin();
    render('identity_register', [], "Enregistrer une identité — Console d'Exploitation");
}

function handle_identity_register_submit(): void
{
    $client = requireAdminLogin();

    if (!Session::checkCsrf($_POST['csrf'] ?? null)) {
        Session::flash('error', 'Session expirée, merci de réessayer.');
        redirect('/identities/new');
    }

    $type = trim((string) ($_POST['identity_type'] ?? ''));
    $response = $client->callAsAdmin('POST', '/identities', [], ['identity_type' => $type], [
        'Idempotency-Key' => bin2hex(random_bytes(16)),
    ]);

    if ($response->forbidden()) {
        Session::flash('error', forbiddenMessage());
        redirect('/identities/new');
    }
    if (!$response->ok()) {
        $detail = $response->data['detail'] ?? $response->data['error'] ?? 'Échec de l\'enregistrement.';
        Session::flash('error', is_string($detail) ? $detail : 'Échec de l\'enregistrement.');
        redirect('/identities/new');
    }

    Session::flash('success', 'Identité ' . ($response->data['identity_id'] ?? '') . ' enregistrée.');
    redirect('/identities/' . ($response->data['identity_id'] ?? ''));
}

function handle_identity_detail(string $identityId): void
{
    $client = requireAdminLogin();
    $response = $client->callAsAdmin('GET', '/identities/' . rawurlencode($identityId));

    if ($response->forbidden()) {
        Session::flash('error', forbiddenMessage());
        render('identity_detail', ['identity' => null, 'identityId' => $identityId], "Identité — Console d'Exploitation");
        return;
    }
    if ($response->status === 404) {
        render('identity_detail', ['identity' => null, 'identityId' => $identityId], "Identité — Console d'Exploitation");
        return;
    }

    render('identity_detail', [
        'identity' => $response->data,
        'identityId' => $identityId,
    ], "Identité {$identityId} — Console d'Exploitation");
}

function handle_identity_transition_confirm(string $identityId, string $transition): void
{
    requireAdminLogin();
    $labels = ['activate' => 'activer', 'suspend' => 'suspendre', 'archive' => 'archiver', 'revoke' => 'révoquer'];

    render('confirm', [
        'message' => 'Confirmer : ' . ($labels[$transition] ?? $transition) . ' l\'identité ' . $identityId . ' ?',
        'actionUrl' => '/identities/' . rawurlencode($identityId) . '/transition/' . $transition,
        'cancelUrl' => '/identities/' . rawurlencode($identityId),
        'csrf' => Session::csrfToken(),
        'danger' => in_array($transition, ['suspend', 'archive', 'revoke'], true),
    ], "Confirmer — Console d'Exploitation");
}

function handle_identity_transition_submit(string $identityId, string $transition): void
{
    $client = requireAdminLogin();

    if (!Session::checkCsrf($_POST['csrf'] ?? null)) {
        Session::flash('error', 'Session expirée, merci de réessayer.');
        redirect('/identities/' . rawurlencode($identityId));
    }

    $response = $client->callAsAdmin('POST', '/identities/' . rawurlencode($identityId) . '/' . $transition);

    if ($response->forbidden()) {
        Session::flash('error', forbiddenMessage());
    } elseif (!$response->ok()) {
        $detail = $response->data['detail'] ?? $response->data['error'] ?? 'Transition refusée.';
        Session::flash('error', is_string($detail) ? $detail : 'Transition refusée.');
    } else {
        Session::flash('success', 'Transition appliquée.');
    }

    redirect('/identities/' . rawurlencode($identityId));
}

function handle_persons_screen(): void
{
    $client = requirePersonLogin();

    $personId = trim((string) ($_GET['person_id'] ?? ''));
    $person = null;
    $personNotFound = false;

    if ($personId !== '') {
        $response = $client->callAsPerson('GET', '/persons/' . rawurlencode($personId));
        if ($response->status === 404) {
            $personNotFound = true;
        } elseif ($response->ok()) {
            $person = $response->data;
        } else {
            Session::flash('error', 'Échec de la recherche.');
        }
    }

    render('persons', [
        'person' => $person,
        'personId' => $personId,
        'personNotFound' => $personNotFound,
    ], "Personnes — Console d'Exploitation");
}

function handle_person_register_submit(): void
{
    $client = requirePersonLogin();

    if (!Session::checkCsrf($_POST['csrf'] ?? null)) {
        Session::flash('error', 'Session expirée, merci de réessayer.');
        redirect('/personnes');
    }

    $identityId = trim((string) ($_POST['identity_id'] ?? ''));
    $declaredName = trim((string) ($_POST['declared_name'] ?? ''));
    $contact = trim((string) ($_POST['contact'] ?? ''));

    $response = $client->callAsPerson('POST', '/persons', [], [
        'identity_id' => $identityId,
        'declared_name' => $declaredName,
        'contact' => $contact,
    ]);

    if (!$response->ok()) {
        $detail = $response->data['detail'] ?? $response->data['error'] ?? 'Échec de l\'enregistrement.';
        Session::flash('error', is_string($detail) ? $detail : 'Échec de l\'enregistrement.');
        redirect('/personnes');
    }

    Session::flash('success', 'Personne ' . ($response->data['person_id'] ?? '') . ' enregistrée.');
    redirect('/personnes?person_id=' . rawurlencode((string) ($response->data['person_id'] ?? '')));
}

function handle_person_account_create_submit(string $personId): void
{
    $client = requirePersonLogin();

    if (!Session::checkCsrf($_POST['csrf'] ?? null)) {
        Session::flash('error', 'Session expirée, merci de réessayer.');
        redirect('/personnes?person_id=' . rawurlencode($personId));
    }

    $response = $client->callAsPerson('POST', '/persons/' . rawurlencode($personId) . '/account');

    if (!$response->ok()) {
        $detail = $response->data['detail'] ?? $response->data['error'] ?? 'Échec de la création du compte.';
        Session::flash('error', is_string($detail) ? $detail : 'Échec de la création du compte.');
    } else {
        Session::flash('success', 'Compte créé pour ' . $personId . '.');
    }

    redirect('/personnes?person_id=' . rawurlencode($personId));
}

/**
 * DIRECTIVE-006 Task 10 — GAM-GAT-ORG-000001 (GAMAD SAS) is the realm's one
 * and only root organization (GENESIS-011 §2.1); the API has no "list all
 * organizations" route (mirrors DIRECTIVE-005's choice for /personnes), so
 * this screen always starts from that known root, exactly as GET
 * /organizations/{orgId}/children does server-side.
 */
function gamadRootOrganizationId(): string
{
    return 'GAM-GAT-ORG-000001';
}

function handle_organizations_screen(): void
{
    $client = requirePersonLogin();

    $root = $client->callAsPerson('GET', '/organizations/' . gamadRootOrganizationId());
    $children = $client->callAsPerson('GET', '/organizations/' . gamadRootOrganizationId() . '/children');

    render('organizations', [
        'root' => $root->ok() ? $root->data : null,
        'rootNotFound' => $root->status === 404,
        'children' => $children->ok() ? ($children->data['items'] ?? []) : [],
    ], "Organisations — Console d'Exploitation");
}

function handle_organization_create_submit(): void
{
    $client = requirePersonLogin();

    if (!Session::checkCsrf($_POST['csrf'] ?? null)) {
        Session::flash('error', 'Session expirée, merci de réessayer.');
        redirect('/organisations');
    }

    $identityId = trim((string) ($_POST['identity_id'] ?? ''));
    $name = trim((string) ($_POST['name'] ?? ''));
    $parentId = trim((string) ($_POST['parent_id'] ?? ''));

    $response = $client->callAsPerson('POST', '/organizations', [], array_filter([
        'identity_id' => $identityId,
        'name' => $name,
        'parent_id' => $parentId !== '' ? $parentId : null,
    ], static fn (mixed $value): bool => $value !== null));

    if (!$response->ok()) {
        $detail = $response->data['detail'] ?? $response->data['error'] ?? 'Échec de la création.';
        Session::flash('error', is_string($detail) ? $detail : 'Échec de la création.');
        redirect('/organisations');
    }

    Session::flash('success', 'Organisation ' . ($response->data['organization_id'] ?? '') . ' créée.');
    redirect('/organisations/' . rawurlencode((string) ($response->data['organization_id'] ?? '')));
}

function handle_organization_detail(string $orgId): void
{
    $client = requirePersonLogin();

    $organization = $client->callAsPerson('GET', '/organizations/' . rawurlencode($orgId));
    $children = $client->callAsPerson('GET', '/organizations/' . rawurlencode($orgId) . '/children');

    render('organization_detail', [
        'organization' => $organization->ok() ? $organization->data : null,
        'organizationNotFound' => $organization->status === 404,
        'orgId' => $orgId,
        'children' => $children->ok() ? ($children->data['items'] ?? []) : [],
    ], "Organisation {$orgId} — Console d'Exploitation");
}

function handle_department_create_submit(string $orgId): void
{
    $client = requirePersonLogin();

    if (!Session::checkCsrf($_POST['csrf'] ?? null)) {
        Session::flash('error', 'Session expirée, merci de réessayer.');
        redirect('/organisations/' . rawurlencode($orgId));
    }

    $name = trim((string) ($_POST['name'] ?? ''));
    $response = $client->callAsPerson('POST', '/organizations/' . rawurlencode($orgId) . '/departments', [], ['name' => $name]);

    if (!$response->ok()) {
        $detail = $response->data['detail'] ?? $response->data['error'] ?? 'Échec de la création du département.';
        Session::flash('error', is_string($detail) ? $detail : 'Échec de la création du département.');
    } else {
        Session::flash('success', 'Département ' . ($response->data['name'] ?? '') . ' créé.');
    }

    redirect('/organisations/' . rawurlencode($orgId));
}

function handle_organization_memberships_screen(string $orgId): void
{
    $client = requirePersonLogin();

    $organization = $client->callAsPerson('GET', '/organizations/' . rawurlencode($orgId));
    $memberships = $client->callAsPerson('GET', '/organizations/' . rawurlencode($orgId) . '/memberships');

    render('organization_memberships', [
        'organization' => $organization->ok() ? $organization->data : null,
        'orgId' => $orgId,
        'items' => $memberships->ok() ? ($memberships->data['items'] ?? []) : [],
    ], "Membres — {$orgId} — Console d'Exploitation");
}

function handle_membership_create_submit(string $orgId): void
{
    $client = requirePersonLogin();

    if (!Session::checkCsrf($_POST['csrf'] ?? null)) {
        Session::flash('error', 'Session expirée, merci de réessayer.');
        redirect('/organisations/' . rawurlencode($orgId) . '/membres');
    }

    $personId = trim((string) ($_POST['person_id'] ?? ''));
    $membershipType = trim((string) ($_POST['membership_type'] ?? ''));
    $departmentId = trim((string) ($_POST['department_id'] ?? ''));

    $response = $client->callAsPerson('POST', '/organizations/' . rawurlencode($orgId) . '/memberships', [], array_filter([
        'person_id' => $personId,
        'membership_type' => $membershipType,
        'department_id' => $departmentId !== '' ? $departmentId : null,
    ], static fn (mixed $value): bool => $value !== null));

    if (!$response->ok()) {
        $detail = $response->data['detail'] ?? $response->data['error'] ?? 'Échec de la création du membership.';
        Session::flash('error', is_string($detail) ? $detail : 'Échec de la création du membership.');
    } else {
        Session::flash('success', 'Membership créé pour ' . $personId . '.');
    }

    redirect('/organisations/' . rawurlencode($orgId) . '/membres');
}

function handle_membership_end_submit(string $membershipId): void
{
    $client = requirePersonLogin();
    $orgId = trim((string) ($_POST['org_id'] ?? ''));

    if (!Session::checkCsrf($_POST['csrf'] ?? null)) {
        Session::flash('error', 'Session expirée, merci de réessayer.');
        redirect('/organisations/' . rawurlencode($orgId) . '/membres');
    }

    $response = $client->callAsPerson('POST', '/memberships/' . rawurlencode($membershipId) . '/end');

    if (!$response->ok()) {
        $detail = $response->data['detail'] ?? $response->data['error'] ?? 'Échec de la fin du membership.';
        Session::flash('error', is_string($detail) ? $detail : 'Échec de la fin du membership.');
    } else {
        Session::flash('success', 'Membership terminé.');
    }

    redirect('/organisations/' . rawurlencode($orgId) . '/membres');
}

function handle_dead_letters_list(): void
{
    $client = requireAdminLogin();
    $response = $client->callAsAdmin('GET', '/admin/runtime/dead-letters');

    if ($response->forbidden()) {
        Session::flash('error', forbiddenMessage());
        render('dead_letters', ['items' => [], 'detail' => null], "Dead letters — Console d'Exploitation");
        return;
    }

    render('dead_letters', [
        'items' => $response->data ?? [],
        'detail' => null,
    ], "Dead letters — Console d'Exploitation");
}

function handle_dead_letter_detail(string $messageId): void
{
    $client = requireAdminLogin();
    $listResponse = $client->callAsAdmin('GET', '/admin/runtime/dead-letters');
    $detailResponse = $client->callAsAdmin('GET', '/admin/runtime/dead-letters/' . rawurlencode($messageId));

    render('dead_letters', [
        'items' => $listResponse->ok() ? ($listResponse->data ?? []) : [],
        'detail' => $detailResponse->ok() ? $detailResponse->data : null,
        'detailNotFound' => $detailResponse->status === 404,
    ], "Dead letter {$messageId} — Console d'Exploitation");
}

function handle_dead_letter_replay_confirm(string $messageId): void
{
    requireAdminLogin();
    render('confirm', [
        'message' => 'Confirmer : rejouer le message ' . $messageId . ' ?',
        'actionUrl' => '/dead-letters/' . rawurlencode($messageId) . '/replay',
        'cancelUrl' => '/dead-letters/' . rawurlencode($messageId),
        'csrf' => Session::csrfToken(),
        'danger' => false,
    ], "Confirmer — Console d'Exploitation");
}

function handle_dead_letter_replay_submit(string $messageId): void
{
    $client = requireAdminLogin();

    if (!Session::checkCsrf($_POST['csrf'] ?? null)) {
        Session::flash('error', 'Session expirée, merci de réessayer.');
        redirect('/dead-letters/' . rawurlencode($messageId));
    }

    $response = $client->callAsAdmin('POST', '/admin/runtime/dead-letters/' . rawurlencode($messageId) . '/replay');

    if ($response->forbidden()) {
        Session::flash('error', forbiddenMessage());
    } elseif ($response->status === 404) {
        Session::flash('error', 'Message introuvable.');
    } elseif (!$response->ok()) {
        Session::flash('error', 'Échec du rejeu.');
    } else {
        Session::flash('success', 'Rejeu déclenché.');
    }

    redirect('/dead-letters/' . rawurlencode($messageId));
}
