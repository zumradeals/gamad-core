<?php

declare(strict_types=1);

/**
 * Correction de frontière CAP-CORE-001 → CAP-CORE-002 (fiche CAP-CORE-002 §14).
 *
 * Ce parcours prouve que `Ctr01::resoudreLiensOrganisations()` :
 *   1. conserve sa signature et son comportement historiques (sur
 *      `relation_organisation`) tant qu'aucun magasin CAP-CORE-002 n'est
 *      fourni — aucun appelant existant du dépôt n'est cassé par ce chantier ;
 *   2. délègue EXCLUSIVEMENT à `organisation_affiliation` dès qu'un magasin
 *      CAP-CORE-002 est fourni, sans plus jamais lire `relation_organisation` ;
 *   3. refuse toute nouvelle écriture organisationnelle par
 *      `Ctr01::rattacherOrganisation()` une fois raccordé, en pointant vers
 *      `RegistreOrganisations::proposerAffiliation()`.
 *
 * Exécution depuis la racine du dépôt :
 *   php apps/console-laravel/tests/Integration/organisations_ctr01_compat_p1.php
 */

use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreIdentites\PolitiqueInscription;
use Gamad\RegistreNormes\BaselineOperationnelle;
use Gamad\RegistreNormes\Db;
use Gamad\RegistreOrganisations\Magasin as OrganisationsMagasin;
use Gamad\RegistreOrganisations\PolitiqueOrganisations;
use Gamad\RegistreOrganisations\RegistreOrganisations;

$application = dirname(__DIR__, 2);
require $application . '/vendor/autoload.php';

$prefixe = sys_get_temp_dir() . '/gamad-ctr01-compat-' . getmypid();
$fichiers = [
    'index' => $prefixe . '-index.sqlite',
    'identites' => $prefixe . '-identites.sqlite',
    'organisations' => $prefixe . '-organisations.sqlite',
];
foreach ($fichiers as $fichier) {
    @unlink($fichier);
}
register_shutdown_function(static function () use ($fichiers): void {
    foreach ($fichiers as $fichier) {
        @unlink($fichier);
    }
});

putenv('DATABASE_URL=');
putenv('SQLITE_PATH=' . $fichiers['index']);
$index = Db::connect();
BaselineOperationnelle::standard()->reconstruire($index);
$registreIdentites = IdentiteMagasin::connecter($fichiers['identites']);
$magasinOrganisations = OrganisationsMagasin::connecter($fichiers['organisations']);

$echecs = 0;
$verifier = static function (bool $ok, string $libelle) use (&$echecs): void {
    printf("  %s  %s\n", $ok ? '[OK]  ' : '[ÉCHEC]', $libelle);
    if (!$ok) {
        $echecs++;
    }
};

echo "INTÉGRATION — COMPATIBILITÉ CTR-01 / CAP-CORE-002 (frontière CAP-CORE-001 §14)\n\n";

$identitePersonne = (new Ctr01($index, $registreIdentites))->inscrireIdentite([
    'canal' => 'AUTORITE', 'type' => 'personne', 'libelle' => 'Compat P1 Personne',
    'producteur' => PolitiqueInscription::AUTORITE_INSCRIPTION, 'politique' => 'POL-COMPAT-P1',
    'source' => 'épreuve compat', 'preuve' => 'EVT-COMPAT-P1-PER-001',
]);
$IDN = (string) $identitePersonne['reference'];
$identiteOrganisation = (new Ctr01($index, $registreIdentites))->inscrireIdentite([
    'canal' => 'AUTORITE', 'type' => 'organisation', 'libelle' => 'Compat P1 Organisation',
    'producteur' => PolitiqueInscription::AUTORITE_INSCRIPTION, 'politique' => 'POL-COMPAT-P1',
    'source' => 'épreuve compat', 'preuve' => 'EVT-COMPAT-P1-ORG-001',
]);
$IDN_ORG = (string) $identiteOrganisation['reference'];

// 1 — comportement historique conservé sans magasin CAP-CORE-002 fourni.
$ctr01Legacy = new Ctr01($index, $registreIdentites);
$rattachementLegacy = $ctr01Legacy->rattacherOrganisation($IDN, $IDN_ORG, 'MEMBRE', [
    'politique' => 'POL-COMPAT-P1', 'producteur' => PolitiqueInscription::AUTORITE_INSCRIPTION,
    'source' => 'épreuve compat', 'preuve' => 'EVT-COMPAT-P1-REL-001',
]);
$liensLegacy = $ctr01Legacy->resoudreLiensOrganisations($IDN);
$verifier(
    !isset($rattachementLegacy['refus'])
        && $liensLegacy !== []
        && $liensLegacy[0]['organisation_reference'] === $IDN_ORG
        && $liensLegacy[0]['relation_type'] === 'MEMBRE',
    'sans magasin CAP-CORE-002, Ctr01 continue de lire/écrire relation_organisation comme avant ce chantier',
);

// 2 — dès qu'un magasin CAP-CORE-002 est fourni, la lecture ne montre PLUS
// la relation historique (elle vit dans une table que Ctr01 ne consulte
// plus) : il n'y a par construction aucun repli implicite vers l'ancienne
// table (fiche §14.4, §25, §32).
$ctr01Delegue = new Ctr01($index, $registreIdentites, $magasinOrganisations);
$liensDelegues = $ctr01Delegue->resoudreLiensOrganisations($IDN);
$verifier(
    $liensDelegues === [],
    'raccordé à CAP-CORE-002, Ctr01 ne lit plus jamais relation_organisation — la relation historique n’y apparaît plus',
);

// 3 — l'écriture organisationnelle est explicitement dépréciée, pas
// silencieusement ignorée.
$rattachementDeprecie = $ctr01Delegue->rattacherOrganisation($IDN, $IDN_ORG, 'MEMBRE', [
    'politique' => 'POL-COMPAT-P1', 'producteur' => PolitiqueInscription::AUTORITE_INSCRIPTION,
    'source' => 'épreuve compat', 'preuve' => 'EVT-COMPAT-P1-REL-002',
]);
$verifier(
    ($rattachementDeprecie['refus'] ?? null) === 'DEPRECIE_CAP_CORE_002',
    'Ctr01::rattacherOrganisation() est explicitement dépréciée une fois raccordée à CAP-CORE-002 — jamais un échec silencieux',
);

// 4 — une affiliation créée via CAP-CORE-002 apparaît dans la projection
// compatible de Ctr01, avec les mêmes noms de champs que l’ancienne lecture,
// et une réponse honnête (jamais opposable) tant qu’aucun mandat n’est vérifié.
$ctr01ForRegistre = new Ctr01($index, $registreIdentites);
$registre = new RegistreOrganisations($index, $registreIdentites, $magasinOrganisations, $ctr01ForRegistre);
$g = static fn (array $extra = []): array => $extra + [
    'politique' => PolitiqueOrganisations::POLITIQUE, 'source' => PolitiqueOrganisations::SOURCE,
    'producteur' => PolitiqueOrganisations::AUTORITE, 'preuve' => 'EVT-COMPAT-P1-' . strtoupper(bin2hex(random_bytes(4))),
];
$inscription = $registre->inscrireOrganisation($g([
    'identite_reference' => $IDN_ORG, 'type_organisation_reference' => 'ASSOCIATION',
    'proprietaire_reference' => PolitiqueInscription::AUTORITE_INSCRIPTION,
    'denomination_officielle' => 'Compat P1 Organisation', 'classification_reference' => 'INTERNE',
]));
$registre->activerOrganisation($inscription['reference'], $g());
$affiliation = $registre->proposerAffiliation($g([
    'organisation_reference' => $inscription['reference'], 'identite_reference' => $IDN,
    'type_affiliation_reference' => 'DIRIGEANT', 'niveau_assurance_reference' => 'A2',
    'classification_reference' => 'INTERNE', 'producteur_reference' => $inscription['reference'],
]));
$registre->activerAffiliation($affiliation['reference'], $g());

$liensApresAffiliation = $ctr01Delegue->resoudreLiensOrganisations($IDN);
$champsAttendus = ['reference', 'organisation_reference', 'relation_type', 'etat', 'assurance', 'mandat_reference', 'mandat_verifie', 'opposable', 'source', 'date_debut', 'date_fin', 'classification'];
$verifier(
    count($liensApresAffiliation) === 1
        && array_keys($liensApresAffiliation[0]) === $champsAttendus
        && $liensApresAffiliation[0]['organisation_reference'] === $IDN_ORG
        && $liensApresAffiliation[0]['relation_type'] === 'DIRIGEANT'
        && $liensApresAffiliation[0]['etat'] === 'ACTIVE'
        && $liensApresAffiliation[0]['opposable'] === false
        && $liensApresAffiliation[0]['mandat_verifie'] === false,
    'une affiliation CAP-CORE-002 se projette dans l’interface historique de Ctr01, avec les mêmes champs, jamais opposable sans mandat CAP-CORE-003',
);

echo "\n";
if ($echecs === 0) {
    echo "Compatibilité CTR-01 / CAP-CORE-002 : ÉTABLIE.\n";
    exit(0);
}
echo "Compatibilité CTR-01 / CAP-CORE-002 : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
