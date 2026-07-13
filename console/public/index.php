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
use Gamad\Console\Lib\CoreApiResponse;
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
    return new CoreApiClient(null, Session::token());
}

/**
 * ADR-0016 §5 — a 401 from the Core always sends the operator back to /login;
 * a 403 is shown as a plain, non-technical message, never a raw error body.
 */
function guardUnauthorized(CoreApiResponse $response): void
{
    if ($response->unauthorized()) {
        Session::destroy();
        redirect('/login');
    }
}

function forbiddenMessage(): string
{
    return "Le token utilisé n'a pas le droit d'effectuer cette action.";
}

function requireLogin(): CoreApiClient
{
    if (!Session::isAuthenticated()) {
        redirect('/login');
    }

    return apiClient();
}

/** @param array<string, mixed> $vars */
function render(string $template, array $vars = [], string $pageTitle = "GAMAD Core — Console d'Exploitation"): void
{
    $authenticated = Session::isAuthenticated();
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
    ['GET', '#^/logout$#', 'handle_logout'],
    ['GET', '#^/$#', 'handle_dashboard'],
    ['GET', '#^/identities$#', 'handle_identities_list'],
    ['GET', '#^/identities/new$#', 'handle_identity_register_form'],
    ['POST', '#^/identities$#', 'handle_identity_register_submit'],
    ['GET', '#^/identities/([A-Z0-9-]+)$#', 'handle_identity_detail'],
    ['GET', '#^/identities/([A-Z0-9-]+)/transition/(activate|suspend|archive|revoke)$#', 'handle_identity_transition_confirm'],
    ['POST', '#^/identities/([A-Z0-9-]+)/transition/(activate|suspend|archive|revoke)$#', 'handle_identity_transition_submit'],
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
    if (Session::isAuthenticated()) {
        redirect('/');
    }
    render('login', [], "Connexion — Console d'Exploitation");
}

function handle_login_submit(): void
{
    $token = trim((string) ($_POST['token'] ?? ''));
    if ($token === '') {
        Session::flash('error', 'Le token ne peut pas être vide.');
        redirect('/login');
    }

    $client = new CoreApiClient(null, $token);
    $response = $client->get('/admin/runtime/health');

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
    Session::setToken($token);
    redirect('/');
}

function handle_logout(): void
{
    Session::destroy();
    redirect('/login');
}

function handle_dashboard(): void
{
    $client = requireLogin();

    $health = $client->get('/admin/runtime/health');
    guardUnauthorized($health);
    $outbox = $client->get('/admin/runtime/outbox');
    guardUnauthorized($outbox);
    $audit = $client->get('/admin/runtime/audit/verify');
    guardUnauthorized($audit);

    render('dashboard', [
        'health' => $health,
        'outbox' => $outbox,
        'audit' => $audit,
    ], "Tableau de bord — Console d'Exploitation");
}

function handle_identities_list(): void
{
    $client = requireLogin();

    $filters = [
        'type' => trim((string) ($_GET['type'] ?? '')),
        'status' => trim((string) ($_GET['status'] ?? '')),
        'cursor' => trim((string) ($_GET['cursor'] ?? '')),
    ];
    $query = array_filter($filters, static fn (string $v): bool => $v !== '');

    $response = $client->get('/identities', $query);
    guardUnauthorized($response);

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
    requireLogin();
    render('identity_register', [], "Enregistrer une identité — Console d'Exploitation");
}

function handle_identity_register_submit(): void
{
    $client = requireLogin();

    if (!Session::checkCsrf($_POST['csrf'] ?? null)) {
        Session::flash('error', 'Session expirée, merci de réessayer.');
        redirect('/identities/new');
    }

    $type = trim((string) ($_POST['identity_type'] ?? ''));
    $response = $client->withIdempotencyKey('/identities', ['identity_type' => $type]);
    guardUnauthorized($response);

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
    $client = requireLogin();
    $response = $client->get('/identities/' . rawurlencode($identityId));
    guardUnauthorized($response);

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
    requireLogin();
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
    $client = requireLogin();

    if (!Session::checkCsrf($_POST['csrf'] ?? null)) {
        Session::flash('error', 'Session expirée, merci de réessayer.');
        redirect('/identities/' . rawurlencode($identityId));
    }

    $response = $client->post('/identities/' . rawurlencode($identityId) . '/' . $transition);
    guardUnauthorized($response);

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

function handle_dead_letters_list(): void
{
    $client = requireLogin();
    $response = $client->get('/admin/runtime/dead-letters');
    guardUnauthorized($response);

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
    $client = requireLogin();
    $listResponse = $client->get('/admin/runtime/dead-letters');
    guardUnauthorized($listResponse);

    $detailResponse = $client->get('/admin/runtime/dead-letters/' . rawurlencode($messageId));
    guardUnauthorized($detailResponse);

    render('dead_letters', [
        'items' => $listResponse->ok() ? ($listResponse->data ?? []) : [],
        'detail' => $detailResponse->ok() ? $detailResponse->data : null,
        'detailNotFound' => $detailResponse->status === 404,
    ], "Dead letter {$messageId} — Console d'Exploitation");
}

function handle_dead_letter_replay_confirm(string $messageId): void
{
    requireLogin();
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
    $client = requireLogin();

    if (!Session::checkCsrf($_POST['csrf'] ?? null)) {
        Session::flash('error', 'Session expirée, merci de réessayer.');
        redirect('/dead-letters/' . rawurlencode($messageId));
    }

    $response = $client->post('/admin/runtime/dead-letters/' . rawurlencode($messageId) . '/replay');
    guardUnauthorized($response);

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
