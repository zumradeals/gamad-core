<?php

declare(strict_types=1);

/**
 * Contre-épreuve de la garde de production de `core:fondation:migrer`.
 *
 * Laravel ne charge PAS `.env` lorsque la configuration est en cache : le
 * bootstrapper s'arrête si `bootstrap/cache/config.php` existe. Une garde qui
 * n'interroge que `getenv()` devient donc faussement bloquante après le premier
 * `php artisan optimize` — elle refuse de migrer en annonçant une variable
 * absente, alors que les quatre connexions fonctionnent depuis le cache.
 *
 * C'est arrivé en exploitation le 1er août 2026. Cette épreuve le rejoue.
 *
 * Exécution depuis la racine du dépôt :
 *   php apps/console-laravel/tests/Integration/migration_config_cache_p1.php
 */

$application = dirname(__DIR__, 2);
$temp = sys_get_temp_dir().'/gamad-migration-cache-'.getmypid();
$caches = [
    'APP_CONFIG_CACHE' => $temp.'-config.php',
    'APP_EVENTS_CACHE' => $temp.'-events.php',
    'APP_PACKAGES_CACHE' => $temp.'-packages.php',
    'APP_ROUTES_CACHE' => $temp.'-routes.php',
    'APP_SERVICES_CACHE' => $temp.'-services.php',
];
register_shutdown_function(static function () use ($caches): void {
    foreach ($caches as $fichier) {
        @unlink($fichier);
    }
});

$echecs = 0;
$verifier = static function (bool $ok, string $libelle) use (&$echecs): void {
    printf("  %s  %s\n", $ok ? '[OK]  ' : '[ÉCHEC]', $libelle);
    if (! $ok) {
        $echecs++;
    }
};

$base = [
    'APP_ENV' => 'production',
    'APP_DEBUG' => 'false',
    'APP_KEY' => 'base64:'.base64_encode(str_repeat('m', 32)),
    'CACHE_STORE' => 'array',
    'SESSION_DRIVER' => 'array',
    'LOG_CHANNEL' => 'errorlog',
] + $caches;

$connexions = [
    'DATABASE_URL' => 'postgresql://exemple@127.0.0.1:1/index_inexistant',
    'MAGASIN_URL' => 'postgresql://exemple@127.0.0.1:1/acces_inexistant',
    'IDENTITY_REGISTRY_URL' => 'postgresql://exemple@127.0.0.1:1/identites_inexistantes',
    'JOURNAL_OPERATIONNEL_URL' => 'postgresql://exemple@127.0.0.1:1/journal_inexistant',
    'PRODUCT_REGISTRY_URL' => 'postgresql://exemple@127.0.0.1:1/produits_inexistants',
    'SOURCE_REGISTRY_URL' => 'postgresql://exemple@127.0.0.1:1/sources_inexistantes',
    'POLICY_REGISTRY_URL' => 'postgresql://exemple@127.0.0.1:1/politiques_inexistantes',
    'CONTRACT_REGISTRY_URL' => 'postgresql://exemple@127.0.0.1:1/contrats_inexistants',
    'VOCABULARY_REGISTRY_URL' => 'postgresql://exemple@127.0.0.1:1/vocabulaire_inexistant',
    'ORGANIZATION_REGISTRY_URL' => 'postgresql://exemple@127.0.0.1:1/organisations_inexistantes',
    'REALM_REGISTRY_URL' => 'postgresql://exemple@127.0.0.1:1/realms_inexistants',
    'EVENT_JOURNAL_URL' => 'postgresql://exemple@127.0.0.1:1/evenements_inexistants',
    'SECRET_REGISTRY_URL' => 'postgresql://exemple@127.0.0.1:1/secrets_inexistants',
    'PROOF_REGISTRY_URL' => 'postgresql://exemple@127.0.0.1:1/preuves_inexistantes',
];

$executer = static function (array $environnement, string $commande) use ($application): string {
    $prefixe = '';
    foreach ($environnement as $cle => $valeur) {
        $prefixe .= escapeshellarg("{$cle}={$valeur}").' ';
    }
    $sortie = [];
    exec(
        'cd '.escapeshellarg($application).' && env -i PATH=/usr/bin:/bin HOME=/tmp '
        .$prefixe.'php artisan '.$commande.' 2>&1',
        $sortie,
    );

    return implode("\n", $sortie);
};

echo "INTÉGRATION — GARDE DE MIGRATION AVEC CONFIGURATION EN CACHE\n\n";

// L'épreuve doit être ÉTANCHE. Sans configuration en cache, Laravel charge le
// `.env` du dépôt — donc les connexions réelles de production : l'épreuve
// toucherait la production et ne prouverait rien d'autre que l'existence de ce
// fichier. Toutes les vérifications passent donc par une configuration mise en
// cache, qui court-circuite le chargement de `.env`.
//
// phpdotenv ne remplace jamais une variable déjà présente : celles fournies ici
// l'emportent sur le `.env`, y compris lorsqu'elles sont vides.
$vides = [
    'DATABASE_URL' => '',
    'MAGASIN_URL' => '',
    'IDENTITY_REGISTRY_URL' => '',
    'JOURNAL_OPERATIONNEL_URL' => '',
    'PRODUCT_REGISTRY_URL' => '',
    'SOURCE_REGISTRY_URL' => '',
    'POLICY_REGISTRY_URL' => '',
    'CONTRACT_REGISTRY_URL' => '',
    'VOCABULARY_REGISTRY_URL' => '',
    'ORGANIZATION_REGISTRY_URL' => '',
    'REALM_REGISTRY_URL' => '',
    'EVENT_JOURNAL_URL' => '',
    'SECRET_REGISTRY_URL' => '',
    'PROOF_REGISTRY_URL' => '',
];

// 1 — connexions vides jusque dans le cache : la garde bloque. C'est son rôle,
// refuser de migrer une production dont on ignore les connexions.
foreach ($caches as $fichier) {
    @unlink($fichier);
}
$executer($base + $vides, 'config:cache');
$sansConnexion = $executer($base + $vides, 'core:fondation:migrer --force');
$verifier(
    str_contains($sansConnexion, 'DATABASE_URL est obligatoire en production'),
    'sans connexion déclarée, la garde refuse toujours de migrer',
);

// 2 — la configuration est mise en cache AVEC les connexions.
foreach ($caches as $fichier) {
    @unlink($fichier);
}
$executer($base + $connexions, 'config:cache');
$verifier(
    is_file($caches['APP_CONFIG_CACHE']),
    'la configuration de production se met en cache avec ses connexions',
);

// 3 — le cache en place, les variables retirées de l'environnement : la garde
// doit lire la configuration plutôt que de déclarer la variable absente.
$avecCache = $executer($base, 'core:fondation:migrer --force');
$verifier(
    ! str_contains($avecCache, 'est obligatoire en production'),
    'configuration en cache : la garde ne déclare plus les connexions absentes',
);

// 4 — la commande échoue bien, mais pour la bonne raison : les bases n'existent
// pas. Une garde franchie ne doit pas laisser croire qu'une migration a eu lieu.
$verifier(
    str_contains($avecCache, 'Migration interrompue')
        || str_contains($avecCache, 'PostgreSQL est obligatoire'),
    'la commande échoue ensuite sur la connexion réelle, pas sur la garde',
);

echo "\n";
if ($echecs === 0) {
    echo "Garde de migration P1 : ÉTABLIE.\n";
    exit(0);
}

echo "Garde de migration P1 : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
