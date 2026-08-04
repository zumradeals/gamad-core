<?php

declare(strict_types=1);

/**
 * Intégration des commandes d'exploitation ajoutées pour CAP-CORE-014
 * (fiche partie 5 §1) : `core:evenements:traiter-rejeux`,
 * `core:evenements:purger-charges`, `core:evenements:rapprocher`.
 *
 * Les fiches d'événement manipulées ici sont insérées directement en SQL
 * plutôt que via le parcours API complet (déjà éprouvé par
 * `evenements_v1_p1.php`) : ce test cible spécifiquement le comportement des
 * commandes artisan, pas le parcours de publication.
 *
 * Exécution depuis la racine du dépôt :
 *   php apps/console-laravel/tests/Integration/evenements_commandes_p1.php
 */

use Gamad\JournalEvenements\Magasin as EvenementsMagasin;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreNormes\BaselineOperationnelle;
use Gamad\RegistreNormes\Db;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

$application = dirname(__DIR__, 2);
$temp = sys_get_temp_dir() . '/gamad-evenements-commandes-' . getmypid();
$fichiers = [
    'index' => $temp . '-index.sqlite',
    'acces' => $temp . '-acces.sqlite',
    'identites' => $temp . '-identites.sqlite',
    'journal' => $temp . '-journal.sqlite',
    'politiques' => $temp . '-politiques.sqlite',
    'contrats' => $temp . '-contrats.sqlite',
    'sources' => $temp . '-sources.sqlite',
    'realms' => $temp . '-realms.sqlite',
    'produits' => $temp . '-produits.sqlite',
    'evenements' => $temp . '-evenements.sqlite',
];
foreach ($fichiers as $fichier) {
    @unlink($fichier);
}
register_shutdown_function(static function () use ($fichiers): void {
    foreach ($fichiers as $fichier) {
        @unlink($fichier);
    }
});

$environnement = [
    'APP_ENV' => 'testing',
    'APP_DEBUG' => 'false',
    'APP_KEY' => 'base64:' . base64_encode(str_repeat('c', 32)),
    'CACHE_STORE' => 'array',
    'SESSION_DRIVER' => 'array',
    'LOG_CHANNEL' => 'errorlog',
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
    'CONTRACT_REGISTRY_URL' => '',
    'CONTRACT_REGISTRY_PATH' => $fichiers['contrats'],
    'SOURCE_REGISTRY_URL' => '',
    'SOURCE_REGISTRY_PATH' => $fichiers['sources'],
    'REALM_REGISTRY_URL' => '',
    'REALM_REGISTRY_PATH' => $fichiers['realms'],
    'PRODUCT_REGISTRY_URL' => '',
    'PRODUCT_REGISTRY_PATH' => $fichiers['produits'],
    'EVENT_JOURNAL_URL' => '',
    'EVENT_JOURNAL_PATH' => $fichiers['evenements'],
];
foreach ($environnement as $cle => $valeur) {
    putenv("{$cle}={$valeur}");
    $_ENV[$cle] = $valeur;
    $_SERVER[$cle] = $valeur;
}

require $application . '/vendor/autoload.php';

$app = require $application . '/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$echecs = 0;
$verifier = static function (bool $ok, string $libelle, string $detail = '') use (&$echecs): void {
    printf("  %s  %s%s\n", $ok ? '[OK]  ' : '[ÉCHEC]', $libelle, $detail !== '' ? "\n           " . $detail : '');
    if (!$ok) {
        $echecs++;
    }
};

/**
 * Chaque commande artisan ouvre sa propre connexion SQLite. SQLite ne
 * tolère pas deux connexions PDO simultanées sur le même fichier dès que
 * l'une écrit : exécuter chaque requête de vérification dans une fonction
 * dédiée garantit que le PDO et ses PDOStatement sortent de portée — donc
 * relâchent le verrou — avant l'appel artisan suivant, sans dépendre du
 * ramasse-miettes du script principal.
 *
 * @template T
 * @param callable(\PDO):T $callback
 * @return T
 */
$avecMagasin = static function (callable $callback) {
    $magasin = EvenementsMagasin::connecter();

    return $callback($magasin);
};

echo "INTÉGRATION — COMMANDES D'EXPLOITATION CAP-CORE-014 (partie 5 §1)\n\n";

BaselineOperationnelle::standard()->reconstruire(Db::connect());
IdentiteMagasin::connecter();

Artisan::call('core:politiques:bootstrap');
Artisan::call('core:evenements:bootstrap');

$ABN = 'ABN-P1-COMMANDES';
$sequences = $avecMagasin(function (\PDO $magasin) use ($ABN): array {
    $magasin->prepare(
        "INSERT INTO abonnement_evenement
         (reference,nom,consommateur_capacite_reference,consommateur_reference,realm_reference,finalite_reference,
          mode_livraison,taille_lot_max,duree_bail_secondes,tentatives_max,cree_par_reference,source_reference,cree_le)
         VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)"
    )->execute([$ABN, 'Abonnement P1 commandes', 'AUT-GAMAD-001', 'AUT-GAMAD-001', 'RLM-P1-COMMANDES', 'FINALITE-P1', 'PULL_API', 100, 300, 8, 'AUT-GAMAD-001', 'garde p1', gmdate('c')]);

    foreach (['EVT-P1-CMD-1', 'EVT-P1-CMD-2'] as $reference) {
        $magasin->prepare(
            "INSERT INTO evenement_commun
             (reference,type_evenement,contrat_reference,contrat_version,producteur_reference,source_reference,
              realm_reference,finalite_reference,correlation_id,idempotence_reference,survenu_le,enregistre_le,
              classification,schema_empreinte,charge_empreinte,empreinte_evenement)
             VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
        )->execute([
            $reference, 'PRODUIT_ACTIVE', 'CTR-P1-COMMANDES', '1.0.0', 'CAP-CORE-999', 'SRC-P1-COMMANDES',
            'RLM-P1-COMMANDES', 'FINALITE-P1', 'COR-P1-' . $reference, 'IDEMP-P1-' . $reference, gmdate('c'), gmdate('c'),
            'INTERNE', str_repeat('a', 64), str_repeat('b', 64), str_repeat('c', 64) . $reference,
        ]);
    }

    return $magasin->query("SELECT sequence_id FROM evenement_commun WHERE reference LIKE 'EVT-P1-CMD-%' ORDER BY sequence_id")->fetchAll(PDO::FETCH_COLUMN);
});

$referenceRejeu = 'REJ-P1-COMMANDES';
$avecMagasin(function (\PDO $magasin) use ($referenceRejeu, $ABN, $sequences): void {
    $magasin->prepare(
        "INSERT INTO demande_rejeu
         (reference,abonnement_reference,sequence_debut,sequence_fin,types_json,motif,etat,demandeur_reference,
          politique_reference,preuve_reference,volume_estime,cree_le)
         VALUES(?,?,?,?,?,?,?,?,?,?,?,?)"
    )->execute([$referenceRejeu, $ABN, $sequences[0], $sequences[1], '[]', 'garde p1', 'VALIDEE', 'AUT-GAMAD-001', 'POL-EVENEMENTS-V1', 'EVT-P1-PREUVE', 2, gmdate('c')]);
});

$sortie = Artisan::call('core:evenements:traiter-rejeux', ['--limite' => 1]);
$rapportTraitement = Artisan::output();
$ligneDemande = $avecMagasin(function (\PDO $magasin) use ($referenceRejeu): array {
    $st = $magasin->prepare('SELECT etat, curseur_sequence FROM demande_rejeu WHERE reference = ?');
    $st->execute([$referenceRejeu]);

    return $st->fetch(PDO::FETCH_ASSOC);
});
$verifier(
    $sortie === 0 && $ligneDemande['etat'] === 'TERMINEE' && (int) $ligneDemande['curseur_sequence'] === (int) $sequences[1]
        && str_contains($rapportTraitement, $referenceRejeu),
    'traiter-rejeux vide une demande VALIDEE de deux événements malgré une limite de un par lot',
);

$evenementCharge = 'EVT-P1-CHARGE-EXPIREE';
$avecMagasin(function (\PDO $magasin) use ($evenementCharge): void {
    $magasin->prepare(
        "INSERT INTO evenement_commun
         (reference,type_evenement,contrat_reference,contrat_version,producteur_reference,source_reference,
          realm_reference,finalite_reference,correlation_id,idempotence_reference,survenu_le,enregistre_le,
          classification,schema_empreinte,charge_empreinte,empreinte_evenement,charge_expire_le)
         VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
    )->execute([
        $evenementCharge, 'PRODUIT_ACTIVE', 'CTR-P1-COMMANDES', '1.0.0', 'CAP-CORE-999', 'SRC-P1-COMMANDES',
        'RLM-P1-COMMANDES', 'FINALITE-P1', 'COR-P1-CHARGE', 'IDEMP-P1-CHARGE', gmdate('c'), gmdate('c'),
        'INTERNE', str_repeat('a', 64), str_repeat('b', 64), str_repeat('e', 64), gmdate('c', time() - 3600),
    ]);
    $magasin->prepare(
        "INSERT INTO evenement_charge(evenement_reference,media_type,schema_format,charge_json,empreinte,taille_octets,cree_le,expire_le)
         VALUES(?,?,?,?,?,?,?,?)"
    )->execute([$evenementCharge, 'application/json', 'JSON', '{}', str_repeat('d', 64), 2, gmdate('c'), gmdate('c', time() - 3600)]);
});

Artisan::call('core:evenements:purger-charges');
$simulation = Artisan::output();
$chargePresenteApresSimulation = $avecMagasin(function (\PDO $magasin) use ($evenementCharge): bool {
    $st = $magasin->prepare('SELECT 1 FROM evenement_charge WHERE evenement_reference = ?');
    $st->execute([$evenementCharge]);

    return $st->fetchColumn() !== false;
});
$verifier(
    str_contains($simulation, $evenementCharge) && str_contains($simulation, '--force') && $chargePresenteApresSimulation,
    'purger-charges liste le candidat en simulation par défaut, sans écrire',
);

Artisan::call('core:evenements:purger-charges', ['--force' => true]);
$purgeReelle = Artisan::output();
$etatApresForce = $avecMagasin(function (\PDO $magasin) use ($evenementCharge): array {
    $chargeSt = $magasin->prepare('SELECT 1 FROM evenement_charge WHERE evenement_reference = ?');
    $chargeSt->execute([$evenementCharge]);
    $enveloppeSt = $magasin->prepare('SELECT 1 FROM evenement_commun WHERE reference = ?');
    $enveloppeSt->execute([$evenementCharge]);

    return ['charge_presente' => $chargeSt->fetchColumn() !== false, 'enveloppe_presente' => $enveloppeSt->fetchColumn() !== false];
});
$verifier(
    str_contains($purgeReelle, '1 charge') && !$etatApresForce['charge_presente'],
    'purger-charges --force purge réellement la charge candidate, jamais l’enveloppe',
);
$verifier($etatApresForce['enveloppe_presente'], 'l’enveloppe de l’événement purgé reste lisible');

$avecMagasin(function (\PDO $magasin) use ($ABN): void {
    $magasin->prepare(
        "INSERT INTO livraison_evenement(reference,abonnement_reference,evenement_reference,sequence_evenement,etat,disponible_le,cree_le)
         VALUES('LIV-P1-ORPHELINE', ?, 'EVT-P1-INEXISTANT', 999999, 'DISPONIBLE', ?, ?)"
    )->execute([$ABN, gmdate('c'), gmdate('c')]);
});

Artisan::call('core:evenements:rapprocher');
$rapportRapprochement = Artisan::output();
$verifier(
    str_contains($rapportRapprochement, 'LIV-P1-ORPHELINE') && str_contains($rapportRapprochement, 'livraison_sans_evenement'),
    'rapprocher détecte la livraison orpheline et ne répare rien',
);
$livraisonEncorePresente = $avecMagasin(
    static fn (\PDO $magasin): bool => $magasin->query("SELECT 1 FROM livraison_evenement WHERE reference = 'LIV-P1-ORPHELINE'")->fetchColumn() !== false,
);
$verifier($livraisonEncorePresente, 'rapprocher n’a supprimé aucune ligne : diagnostic seul');

echo "\n";
if ($echecs === 0) {
    echo "Commandes d'exploitation CAP-CORE-014 P1 : ÉTABLIE.\n";
    exit(0);
}
echo "Commandes d'exploitation CAP-CORE-014 P1 : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
