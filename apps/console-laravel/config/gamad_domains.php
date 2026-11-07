<?php

// Activation explicite : aucun domaine ni produit opérationnel n'est inventé.
return [
    'enabled' => (bool) env('GAMAD_MULTIDOMAIN_ENABLED', false),
    'institutional_host' => env('GAMAD_INSTITUTIONAL_HOST', ''),
    'trusted_proxies' => array_values(array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', ''))))),
];
