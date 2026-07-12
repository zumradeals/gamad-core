<?php

declare(strict_types=1);

$stateDirectory = getenv('GAMAD_TEST_IDP_STATE_DIR') ?: dirname(__DIR__, 2) . '/var/test-idp';
$issuer = getenv('GAMAD_TEST_IDP_ISSUER') ?: 'http://127.0.0.1:9090';
$audience = getenv('GAMAD_TEST_IDP_AUDIENCE') ?: 'gamad-admin';
$keyVersion = trim((string) (@file_get_contents($stateDirectory . '/active-key') ?: '1'));
$privateKeyFile = $stateDirectory . '/private-' . $keyVersion . '.pem';
$jwkFile = $stateDirectory . '/jwk-' . $keyVersion . '.json';

if (!is_file($privateKeyFile) || !is_file($jwkFile)) {
    http_response_code(503);
    echo json_encode(['error' => 'test_idp_not_initialized']);
    return;
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
header('Content-Type: application/json');

if ($path === '/.well-known/openid-configuration') {
    echo json_encode([
        'issuer' => $issuer,
        'jwks_uri' => $issuer . '/jwks.json',
        'token_endpoint' => $issuer . '/token',
        'id_token_signing_alg_values_supported' => ['RS256'],
    ], JSON_THROW_ON_ERROR);
    return;
}

if ($path === '/jwks.json') {
    echo json_encode(['keys' => [json_decode((string) file_get_contents($jwkFile), true, flags: JSON_THROW_ON_ERROR)]], JSON_THROW_ON_ERROR);
    return;
}

if ($path === '/token' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $subject = $_POST['sub'] ?? 'GAM-PER-000001';
    $scope = $_POST['scope'] ?? 'core.runtime.health.read';
    $now = time();
    $header = ['typ' => 'JWT', 'alg' => 'RS256', 'kid' => 'test-key-' . $keyVersion];
    $claims = [
        'iss' => $issuer,
        'aud' => $audience,
        'sub' => $subject,
        'scope' => $scope,
        'iat' => $now,
        'nbf' => $now - 1,
        'exp' => $now + 300,
    ];
    $encode = static fn (string $value): string => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    $input = $encode(json_encode($header, JSON_THROW_ON_ERROR)) . '.' . $encode(json_encode($claims, JSON_THROW_ON_ERROR));
    $privateKey = openssl_pkey_get_private((string) file_get_contents($privateKeyFile));
    openssl_sign($input, $signature, $privateKey, OPENSSL_ALGO_SHA256);

    echo json_encode([
        'access_token' => $input . '.' . $encode($signature),
        'token_type' => 'Bearer',
        'expires_in' => 300,
        'scope' => $scope,
    ], JSON_THROW_ON_ERROR);
    return;
}

http_response_code(404);
echo json_encode(['error' => 'not_found']);
