<?php

declare(strict_types=1);

/**
 * Contre-épreuve de DEC-2026-09-08-GAMAD-PRODUIT-005.
 *
 * Prouve qu'un produit 005 déjà inscrit et ACTIF peut prendre le nom GAMAD
 * sans nouvelle inscription, sans changement d'identité associée, de cycle,
 * de type, de propriétaire ni d'autorisation de fédération.
 */

use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreNormes\BaselineOperationnelle;
use Gamad\RegistreNormes\Db;
use Gamad\RegistreProduits\Magasin as ProduitsMagasin;
use Gamad\RegistreProduits\PolitiqueProduits;
use Gamad\RegistreProduits\RegistreProduits;

$application = dirname(__DIR__, 2);
$temp = sys_get_temp_dir().'/gamad-product-005-'.getmypid();
$fichiers = [
    'index' => $temp.'-index.sqlite',
    'identites' => $temp.'-identites.sqlite',
    'produits' => $temp.'-produits.sqlite',
];
$cache = [
    $temp.'-config.php',
    $temp.'-events.php',
    $temp.'-packages.php',
    $temp.'-routes.php',
    $temp.'-services.php',
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
    'APP_KEY' => 'base64:'.base64_encode(str_repeat('g', 32)),
    'APP_CONFIG_CACHE' => $temp.'-config.php',
    'APP_EVENTS_CACHE' => $temp.'-events.php',
    'APP_PACKAGES_CACHE' => $temp.'-packages.php',
    'APP_ROUTES_CACHE' => $temp.'-routes.php',
    'APP_SERVICES_CACHE' => $temp.'-services.php',
    'CACHE_STORE' => 'array',
    'SESSION_DRIVER' => 'array',
    'LOG_CHANNEL' => 'errorlog',
    'DATABASE_URL' => '',
    'SQLITE_PATH' => $fichiers['index'],
    'IDENTITY_REGISTRY_URL' => '',
    'IDENTITY_REGISTRY_PATH' => $fichiers['identites'],
    'PRODUCT_REGISTRY_URL' => '',
    'PRODUCT_REGISTRY_PATH' => $fichiers['produits'],
];
foreach ($environnement as $cle => $valeur) {
    putenv("{$cle}={$valeur}");
    $_ENV[$cle] = $valeur;
    $_SERVER[$cle] = $valeur;
}

require $application.'/vendor/autoload.php';

$index = Db::connect();
BaselineOperationnelle::standard()->reconstruire($index);
$identites = IdentiteMagasin::connecter();
$produits = ProduitsMagasin::connecter();
$ctr01 = new Ctr01($index, $identites, produits: $produits);
$registre = new RegistreProduits($index, $identites, $produits, $ctr01);

$echecs = 0;
$verifier = static function (bool $ok, string $libelle) use (&$echecs): void {
    printf("  %s  %s\n", $ok ? '[OK]  ' : '[ÉCHEC]', $libelle);
    if (!$ok) {
        $echecs++;
    }
};

echo "CONTRE-ÉPREUVE — PRD-GAMAD-005 → GAMAD\n\n";

$identite = $ctr01->inscrireIdentite([
    'canal' => 'CREATION_TECHNIQUE',
    'type' => 'produit',
    'libelle' => 'Portail historique',
    'producteur' => 'AUT-GAMAD-001',
    'politique' => 'POL-INSCRIPTION-IDENTITES-V1',
    'source' => 'TEST-DEC-GAMAD-PRODUIT-005',
    'preuve' => 'TEST-DEC-GAMAD-IDENTITE',
]);
$identiteReference = (string) ($identite['reference'] ?? '');
$verifier(str_starts_with($identiteReference, 'IDN-PRD-'), 'une identité produit distincte est inscrite');

$inscription = $registre->inscrireProduit([
    'reference' => 'PRD-GAMAD-005',
    'identite_reference' => $identiteReference,
    'nom_canonique' => 'Portail historique',
    'nom_affichage' => 'Portail',
    'type_produit' => 'PORTAIL',
    'proprietaire_reference' => 'AUT-GAMAD-001',
    'source' => 'TEST-DEC-GAMAD-PRODUIT-005',
    'producteur' => 'AUT-GAMAD-001',
    'politique' => PolitiqueProduits::POLITIQUE,
    'preuve' => 'TEST-DEC-GAMAD-INSCRIPTION',
]);
$verifier(!isset($inscription['refus']), 'PRD-GAMAD-005 existe avant le renommage');

$federation = $registre->modifierProduit('PRD-GAMAD-005', [
    'federation_autorisee' => true,
    'politique' => PolitiqueProduits::POLITIQUE,
    'producteur' => 'AUT-GAMAD-001',
    'source' => 'TEST-DEC-GAMAD-PRODUIT-005',
    'preuve' => 'TEST-DEC-GAMAD-FEDERATION',
]);
$verifier(!isset($federation['refus']), 'une permission de fédération préexistante est posée');

$activation = $registre->activerProduit('PRD-GAMAD-005', [
    'politique' => PolitiqueProduits::POLITIQUE,
    'producteur' => 'AUT-GAMAD-001',
    'source' => 'TEST-DEC-GAMAD-PRODUIT-005',
    'preuve' => 'TEST-DEC-GAMAD-ACTIVATION',
]);
$verifier(($activation['etat'] ?? null) === 'ACTIF', 'le produit est ACTIF avant le renommage');

$avant = $registre->resoudreProduit('PRD-GAMAD-005');
$etatAvant = $registre->resoudreEtat('PRD-GAMAD-005');

$app = require $application.'/bootstrap/app.php';
$console = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$sortie = new \Symfony\Component\Console\Output\BufferedOutput();
$statut = $console->call('core:produit-gamad:normaliser', [], $sortie);
$verifier($statut === 0, 'la migration gouvernée réussit : '.trim($sortie->fetch()));

$apres = $registre->resoudreProduit('PRD-GAMAD-005');
$etatApres = $registre->resoudreEtat('PRD-GAMAD-005');
$verifier(($apres['nom_canonique'] ?? null) === 'GAMAD', 'nom_canonique = GAMAD');
$verifier(($apres['nom_affichage'] ?? null) === 'GAMAD', 'nom_affichage = GAMAD');

foreach (['reference', 'identite_reference', 'type_produit', 'proprietaire_reference', 'federation_autorisee'] as $champ) {
    $verifier(
        ($apres[$champ] ?? null) === ($avant[$champ] ?? null),
        "{$champ} reste inchangé",
    );
}
$verifier(($etatApres['etat'] ?? null) === ($etatAvant['etat'] ?? null), 'le cycle reste ACTIF et inchangé');

$sortieIdempotente = new \Symfony\Component\Console\Output\BufferedOutput();
$statutIdempotent = $console->call('core:produit-gamad:normaliser', [], $sortieIdempotente);
$verifier($statutIdempotent === 0, 'une seconde exécution est idempotente');
$verifier(
    str_contains($sortieIdempotente->fetch(), 'déjà normalisé'),
    'la seconde exécution annonce explicitement qu’aucune mutation n’est requise',
);

echo "\n".($echecs === 0 ? 'RÉSULTAT : OK' : "RÉSULTAT : {$echecs} ÉCHEC(S)")."\n";
exit($echecs === 0 ? 0 : 1);
