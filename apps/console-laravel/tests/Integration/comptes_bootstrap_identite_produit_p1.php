<?php

declare(strict_types=1);

/**
 * Contre-épreuve de `core:comptes:bootstrap` pour un produit inscrit par la
 * voie normale de la console.
 *
 * Les quatre produits de la baseline (PRD-GAMAD-001 à 004) portent une
 * identité DÉRIVÉE dont la référence vaut littéralement leur propre code
 * (`identite_reference === reference`) — un artefact du bootstrap direct
 * depuis `index-baseline-v1.json`. Un produit créé depuis `/produits/create`
 * suit un chemin différent et réel : l'identité est INSCRITE séparément,
 * avec une référence auto-allouée par `Ctr01::inscrireIdentite()`
 * (`IDN-PRD-...`), distincte du code produit choisi par l'opérateur
 * (`PRD-GAMAD-005`). C'est exactement ainsi que DG AFRIQUE Portal a été créé
 * en production le 7 août 2026.
 *
 * `BootstrapComptesGamadCommand` résolvait l'identité canonique en
 * réutilisant le code produit tel quel, une hypothèse vraie seulement pour
 * la baseline. Sur un produit inscrit via la console, la résolution échouait
 * et la délégation `POL-COMPTES-GAMAD-V1` ne s'activait jamais. Cette épreuve
 * rejoue le cas réel : produit inscrit avec une identité d'auto-référence
 * différente, puis exécution de `core:comptes:bootstrap`.
 *
 * Portée volontairement limitée à cette commande : `Ctr01::produitReconnu()`
 * (canal `PRODUIT_RECONNU`, utilisé ensuite par la création effective d'un
 * Compte GAMAD) porte un écart distinct et préexistant — il ne reconnaît que
 * les produits figés dans `index-baseline-v1.json`, jamais un produit
 * inscrit/activé dynamiquement via CAP-CORE-011. Cet écart n'est pas corrigé
 * ici et reste à traiter séparément.
 *
 * Exécution depuis la racine du dépôt :
 *   php apps/console-laravel/tests/Integration/comptes_bootstrap_identite_produit_p1.php
 */

use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreNormes\BaselineOperationnelle;
use Gamad\RegistreNormes\Db;
use Gamad\RegistrePolitiques\Magasin as PolitiquesMagasin;
use Gamad\RegistrePolitiques\RegistrePolitiques;
use Gamad\RegistreProduits\Magasin as ProduitsMagasin;
use Gamad\RegistreProduits\RegistreProduits;

$application = dirname(__DIR__, 2);
$temp = sys_get_temp_dir() . '/gamad-comptes-bootstrap-' . getmypid();
$fichiers = [
    'index' => $temp . '-index.sqlite',
    'acces' => $temp . '-acces.sqlite',
    'identites' => $temp . '-identites.sqlite',
    'journal' => $temp . '-journal.sqlite',
    'politiques' => $temp . '-politiques.sqlite',
    'produits' => $temp . '-produits.sqlite',
];
$cache = [
    $temp . '-config.php',
    $temp . '-events.php',
    $temp . '-packages.php',
    $temp . '-routes.php',
    $temp . '-services.php',
];
foreach (array_merge(array_values($fichiers), $cache) as $fichier) {
    @unlink($fichier);
}
register_shutdown_function(static function () use ($fichiers, $cache): void {
    foreach (array_merge(array_values($fichiers), $cache) as $fichier) {
        @unlink($fichier);
    }
});

$environnement = [
    'APP_ENV' => 'testing',
    'APP_DEBUG' => 'false',
    'APP_KEY' => 'base64:' . base64_encode(str_repeat('b', 32)),
    'APP_CONFIG_CACHE' => $temp . '-config.php',
    'APP_EVENTS_CACHE' => $temp . '-events.php',
    'APP_PACKAGES_CACHE' => $temp . '-packages.php',
    'APP_ROUTES_CACHE' => $temp . '-routes.php',
    'APP_SERVICES_CACHE' => $temp . '-services.php',
    'CACHE_STORE' => 'array',
    'SESSION_DRIVER' => 'array',
    'LOG_CHANNEL' => 'errorlog',
    'MAIL_MAILER' => 'array',
    'GAMAD_VERIFICATION_EMAIL_ENABLED' => 'false',
    'GAMAD_VERIFICATION_SMS_ENABLED' => 'false',
    'DATABASE_URL' => '',
    'SQLITE_PATH' => $fichiers['index'],
    'MAGASIN_URL' => '',
    'MAGASIN_PATH' => $fichiers['acces'],
    'IDENTITY_REGISTRY_URL' => '',
    'IDENTITY_REGISTRY_PATH' => $fichiers['identites'],
    'JOURNAL_OPERATIONNEL_URL' => '',
    'JOURNAL_OPERATIONNEL_PATH' => $fichiers['journal'],
    'POLICY_REGISTRY_URL' => '',
    'POLICY_REGISTRY_PATH' => $fichiers['politiques'],
    'PRODUCT_REGISTRY_URL' => '',
    'PRODUCT_REGISTRY_PATH' => $fichiers['produits'],
];
foreach ($environnement as $cle => $valeur) {
    putenv("{$cle}={$valeur}");
    $_ENV[$cle] = $valeur;
    $_SERVER[$cle] = $valeur;
}

require $application . '/vendor/autoload.php';

$index = Db::connect();
BaselineOperationnelle::standard()->reconstruire($index);
$registreIdentites = IdentiteMagasin::connecter();
$ctr01 = new Ctr01($index, $registreIdentites);
$registreProduits = new RegistreProduits($index, $registreIdentites, ProduitsMagasin::connecter(), $ctr01);

$app = require $application . '/bootstrap/app.php';
$console = $app->make(\Illuminate\Contracts\Console\Kernel::class);

$echecs = 0;
$verifier = static function (bool $ok, string $libelle) use (&$echecs): void {
    printf("  %s  %s\n", $ok ? '[OK]  ' : '[ÉCHEC]', $libelle);
    if (!$ok) {
        $echecs++;
    }
};

echo "CONTRE-ÉPREUVE — core:comptes:bootstrap SUR UN PRODUIT INSCRIT PAR LA CONSOLE\n\n";

$console->call('core:politiques:bootstrap');
// Volontairement PAS de core:produits:bootstrap : PRD-GAMAD-005 n'appartient
// pas à la baseline. Il est inscrit ici exactement comme la console le ferait
// depuis /produits/create : une identité d'abord, un produit qui la référence
// ensuite — jamais l'inverse.

$identite = $ctr01->inscrireIdentite([
    'canal' => 'CREATION_TECHNIQUE',
    'type' => 'produit',
    'libelle' => 'DG AFRIQUE Portal',
    'producteur' => 'AUT-GAMAD-001',
    'politique' => 'POL-INSCRIPTION-IDENTITES-V1',
    'source' => 'TEST-COMPTES-BOOTSTRAP-IDENTITE-PRODUIT',
    'preuve' => 'TEST-COMPTES-BOOTSTRAP-IDENTITE',
]);
$identiteReference = (string) ($identite['reference'] ?? '');
$verifier(
    str_starts_with($identiteReference, 'IDN-PRD-') && $identiteReference !== 'PRD-GAMAD-005',
    'l’identité inscrite par la console reçoit une référence auto-allouée, distincte du code produit',
);

$inscriptionProduit = $registreProduits->inscrireProduit([
    'reference' => 'PRD-GAMAD-005',
    'identite_reference' => $identiteReference,
    'nom_canonique' => 'DG AFRIQUE Portal',
    'nom_affichage' => 'DG AFRIQUE',
    'type_produit' => 'PORTAIL',
    'proprietaire_reference' => 'AUT-GAMAD-001',
    'source' => 'TEST-COMPTES-BOOTSTRAP-IDENTITE-PRODUIT',
    'producteur' => 'AUT-GAMAD-001',
    'politique' => 'POL-PRODUITS-V1',
    'preuve' => 'TEST-COMPTES-BOOTSTRAP-PRODUIT',
]);
$verifier(
    !isset($inscriptionProduit['refus']) && ($inscriptionProduit['identite_reference'] ?? null) === $identiteReference,
    'PRD-GAMAD-005 est inscrit avec une identite_reference qui n’est pas sa propre référence',
);

$sortie = new \Symfony\Component\Console\Output\BufferedOutput();
$statut = $console->call('core:comptes:bootstrap', [], $sortie);
$verifier(
    $statut === 0,
    'core:comptes:bootstrap réussit pour un produit dont l’identité a une référence différente : ' . trim($sortie->fetch()),
);

$registrePolitiques = new RegistrePolitiques($index, $registreIdentites, PolitiquesMagasin::connecter(), $ctr01);
$version = $registrePolitiques->resoudreVersion('POL-COMPTES-GAMAD-V1', '1.0.0');
$verifier(
    is_array($version) && ($version['etat'] ?? null) === 'ACTIVE',
    'POL-COMPTES-GAMAD-V1 1.0.0 est ACTIVE après le bootstrap',
);

$regle = null;
foreach ((array) ($version['regles'] ?? []) as $candidate) {
    if (($candidate['sujet_reference'] ?? null) === 'PRD-GAMAD-005') {
        $regle = $candidate;
        break;
    }
}
$verifier(
    is_array($regle) && ($regle['effet'] ?? null) === 'PERMET',
    'la règle vise le code produit PRD-GAMAD-005 (pas la référence d’identité), pour rester compatible avec l’authentification par produit',
);

echo "\n";
if ($echecs === 0) {
    echo "Contre-épreuve bootstrap Compte GAMAD / identité produit : ÉTABLIE.\n";
    exit(0);
}

echo "Contre-épreuve bootstrap Compte GAMAD / identité produit : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
