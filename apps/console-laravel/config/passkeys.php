<?php

declare(strict_types=1);

$url = (string) env('APP_URL', 'https://localhost');
$origine = rtrim($url, '/');
$hote = parse_url($origine, PHP_URL_HOST);

return [
    'relying_party' => [
        'name' => env('GAMAD_PASSKEY_RP_NAME', 'GAMAD Core'),
        'id' => env('GAMAD_PASSKEY_RP_ID', is_string($hote) ? $hote : 'localhost'),
    ],

    // Une liste fermée, jamais les valeurs Host/Origin reçues de la requête.
    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('GAMAD_PASSKEY_ALLOWED_ORIGINS', $origine)),
    ))),

    // La recommandation WebAuthn pour une vérification utilisateur est de
    // cinq à dix minutes. Le Core retient le plancher et brûle le challenge à
    // la première réponse, valide ou non.
    'ceremony_timeout_seconds' => 300,
];
