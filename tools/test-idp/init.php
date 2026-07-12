<?php

declare(strict_types=1);

$stateDirectory = getenv('GAMAD_TEST_IDP_STATE_DIR') ?: dirname(__DIR__, 2) . '/var/test-idp';
$version = $argv[1] ?? '1';

if (!is_dir($stateDirectory) && !mkdir($stateDirectory, 0700, true) && !is_dir($stateDirectory)) {
    throw new RuntimeException('Unable to create test IdP state directory.');
}

$key = openssl_pkey_new([
    'private_key_bits' => 2048,
    'private_key_type' => OPENSSL_KEYTYPE_RSA,
]);
if ($key === false) {
    throw new RuntimeException('Unable to generate RSA key.');
}

openssl_pkey_export($key, $privatePem);
$details = openssl_pkey_get_details($key);
if ($details === false || !isset($details['rsa']['n'], $details['rsa']['e'])) {
    throw new RuntimeException('Unable to read generated RSA key.');
}

$encode = static fn (string $value): string => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
$jwk = [
    'kty' => 'RSA',
    'kid' => 'test-key-' . $version,
    'use' => 'sig',
    'alg' => 'RS256',
    'n' => $encode($details['rsa']['n']),
    'e' => $encode($details['rsa']['e']),
];

file_put_contents($stateDirectory . '/private-' . $version . '.pem', $privatePem, LOCK_EX);
chmod($stateDirectory . '/private-' . $version . '.pem', 0600);
file_put_contents($stateDirectory . '/jwk-' . $version . '.json', json_encode($jwk, JSON_THROW_ON_ERROR), LOCK_EX);
file_put_contents($stateDirectory . '/active-key', $version, LOCK_EX);

fwrite(STDOUT, json_encode(['active_key' => $version, 'state_directory' => $stateDirectory], JSON_THROW_ON_ERROR) . PHP_EOL);
