<?php

declare(strict_types=1);

use App\Support\ImportateurSqlite;
use Gamad\RegistreAcces\Magasin as AccesMagasin;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;

$application = dirname(__DIR__, 2);
require $application . '/vendor/autoload.php';

$prefixe = sys_get_temp_dir() . '/gamad-import-p0-' . getmypid();
$fichiers = [
    'acces_source' => $prefixe . '-acces-source.sqlite',
    'acces_cible' => $prefixe . '-acces-cible.sqlite',
    'identites_source' => $prefixe . '-identites-source.sqlite',
    'identites_cible' => $prefixe . '-identites-cible.sqlite',
];
foreach ($fichiers as $fichier) {
    @unlink($fichier);
}
register_shutdown_function(static function () use ($fichiers): void {
    foreach ($fichiers as $fichier) {
        @unlink($fichier);
    }
});

putenv('MAGASIN_URL=');
putenv('IDENTITY_REGISTRY_URL=');
$sourceAcces = new \PDO('sqlite:' . $fichiers['acces_source']);
$sourceAcces->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
$sourceAcces->exec(
    'CREATE TABLE authentificateur (
        reference TEXT PRIMARY KEY, entite_reference TEXT NOT NULL, type TEXT NOT NULL,
        empreinte TEXT NOT NULL, niveau_assurance TEXT NOT NULL, etat TEXT NOT NULL,
        cree_le TEXT NOT NULL, revoque_le TEXT
    )',
);
$sourceAcces->exec(
    'CREATE TABLE session_ouverte (
        id INTEGER PRIMARY KEY AUTOINCREMENT, reference TEXT NOT NULL UNIQUE,
        authentificateur_ref TEXT NOT NULL, entite_reference TEXT NOT NULL,
        niveau_assurance TEXT NOT NULL, ouverte_le TEXT NOT NULL, expire_le TEXT NOT NULL,
        revoquee_le TEXT
    )',
);
$sourceAcces->exec(
    "INSERT INTO authentificateur
     (reference,entite_reference,type,empreinte,niveau_assurance,etat,cree_le)
     VALUES ('AUTHN-IMPORT','AUT-GAMAD-001','mot_de_passe','empreinte-test','AS1','ACTIF','2026-07-30T00:00:00Z')",
);
$sourceAcces->exec(
    "INSERT INTO session_ouverte
     (reference,authentificateur_ref,entite_reference,niveau_assurance,ouverte_le,expire_le)
     VALUES ('SESS-IMPORT','AUTHN-IMPORT','AUT-GAMAD-001','AS1','2026-07-30T00:00:00Z','2026-07-31T00:00:00Z')",
);

$sourceIdentites = IdentiteMagasin::connecter($fichiers['identites_source']);
$sourceIdentites->exec(
    "INSERT INTO compteur_reference_identite(type,dernier) VALUES ('personne',1)",
);
$sourceIdentites->exec(
    "INSERT INTO identite_inscrite
     (reference,type,libelle,regime,provisoire,canal,producteur,
      politique_inscription,source_inscription,preuve_reference,classification,date_creation)
     VALUES ('IDN-PER-000001','personne','Import P0','INSCRIT_AU_REGISTRE',0,
             'AUTORITE','AUT-GAMAD-001','POL-IMPORT','source','EVT-IMPORT','INTERNE','2026-07-30')",
);

$cibleAcces = AccesMagasin::connecter($fichiers['acces_cible']);
$cibleIdentites = IdentiteMagasin::connecter($fichiers['identites_cible']);
$importateur = new ImportateurSqlite();

$echecs = 0;
$verifier = static function (bool $ok, string $libelle) use (&$echecs): void {
    printf("  %s  %s\n", $ok ? '[OK]  ' : '[ÉCHEC]', $libelle);
    if (!$ok) {
        $echecs++;
    }
};

echo "INTÉGRATION — IMPORT SQLITE P0\n\n";
$acces = $importateur->importerAcces($fichiers['acces_source'], $cibleAcces);
$identites = $importateur->importerIdentites($fichiers['identites_source'], $cibleIdentites);
$verifier(
    $acces['authentificateur'] === 1
        && $acces['session_ouverte'] === 1
        && (int) $cibleAcces->query(
            "SELECT count(*) FROM session_ouverte
             WHERE jeton_empreinte = '" . hash('sha256', 'SESS-IMPORT') . "'
               AND reference <> 'SESS-IMPORT'",
        )->fetchColumn() === 1,
    'authentificateurs et sessions sont copiés sans conserver le bearer en clair',
);
$verifier(
    $identites['identite_inscrite'] === 1
        && (string) $cibleIdentites->query(
            "SELECT preuve_reference FROM identite_inscrite WHERE reference = 'IDN-PER-000001'",
        )->fetchColumn() === 'EVT-IMPORT',
    'l’identité persistante conserve sa référence et sa preuve',
);

$secondImportRefuse = false;
try {
    $importateur->importerAcces($fichiers['acces_source'], $cibleAcces);
} catch (\RuntimeException) {
    $secondImportRefuse = true;
}
$verifier($secondImportRefuse, 'une cible non vide est refusée');

echo "\n";
if ($echecs === 0) {
    echo "Import SQLite : ÉTABLI.\n";
    exit(0);
}

echo "Import SQLite : NON ÉTABLI ({$echecs} écart(s)).\n";
exit(1);
