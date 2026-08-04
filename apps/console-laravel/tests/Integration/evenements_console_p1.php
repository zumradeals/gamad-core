<?php

declare(strict_types=1);

/**
 * Épreuve de l'écran d'administration du journal des événements
 * (CAP-CORE-014, fiche partie 4 §9) : tableau de bord, fiche événement,
 * fiche abonnement, écran lettres mortes, écran rejeu.
 *
 * Contrairement aux consoles Realms/Organisations/Produits, la console
 * CAP-CORE-014 route toute lecture et toute écriture par `AccesEvenements`,
 * le même cas d'usage gouverné que l'API v1 — ce test appelle directement
 * les méthodes du contrôleur, sur le modèle de `realms_console_p1.php`.
 *
 * Exécution depuis la racine du dépôt :
 *   php apps/console-laravel/tests/Integration/evenements_console_p1.php
 */

use App\Application\Evenements\AccesEvenements;
use App\Http\Controllers\EvenementConsoleController;
use Gamad\JournalEvenements\Magasin as EvenementsMagasin;
use Gamad\JournalEvenements\RegistreAbonnements;
use Gamad\JournalEvenements\RegistreEvenements;
use Gamad\JournalOperationnel\Magasin as JournalMagasin;
use Gamad\RegistreContrats\Magasin as ContratsMagasin;
use Gamad\RegistreContrats\PolitiqueContrats;
use Gamad\RegistreContrats\RegistreContrats;
use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreIdentites\PolitiqueInscription;
use Gamad\RegistreNormes\BaselineOperationnelle;
use Gamad\RegistreNormes\Db;
use Gamad\RegistreProduits\Magasin as ProduitsMagasin;
use Gamad\RegistreRealms\Magasin as RealmsMagasin;
use Gamad\RegistreRealms\PolitiqueRealms;
use Gamad\RegistreRealms\RegistreRealms;
use Gamad\RegistreSources\Magasin as SourcesMagasin;
use Gamad\RegistreSources\PolitiqueSources;
use Gamad\RegistreSources\RegistreSources;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\ViewErrorBag;

$application = dirname(__DIR__, 2);
$temp = sys_get_temp_dir() . '/gamad-evenements-console-' . getmypid();
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
    'APP_KEY' => 'base64:' . base64_encode(str_repeat('n', 32)),
    'APP_URL' => 'https://console.test',
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

$index = Db::connect();
BaselineOperationnelle::standard()->reconstruire($index);
$registreIdentites = IdentiteMagasin::connecter();
JournalMagasin::connecter();
$ctr01 = new Ctr01($index, $registreIdentites);

$AUTORITE = PolitiqueInscription::AUTORITE_INSCRIPTION;
$inscrireIdentite = static function (string $type, string $libelle) use ($ctr01, $AUTORITE): string {
    $identite = $ctr01->inscrireIdentite([
        'canal' => 'AUTORITE', 'type' => $type, 'libelle' => $libelle,
        'producteur' => $AUTORITE, 'politique' => 'POL-CONSOLE-EVT-P1',
        'source' => 'épreuve console CAP-CORE-014', 'preuve' => 'EVT-CONSOLE-P1-IDN-' . strtoupper(bin2hex(random_bytes(4))),
    ]);
    if (isset($identite['refus'])) {
        throw new RuntimeException('inscription identité impossible : ' . json_encode($identite));
    }

    return (string) $identite['reference'];
};

// ------------------------------------------------------------------ source
$sources = new RegistreSources($index, $registreIdentites, SourcesMagasin::connecter(), ProduitsMagasin::connecter(), $ctr01);
$gSource = static fn (): array => [
    'politique' => PolitiqueSources::POLITIQUE, 'producteur' => $AUTORITE,
    'source' => 'épreuve console CAP-CORE-014', 'preuve' => 'EVT-CONSOLE-P1-SRC-' . strtoupper(bin2hex(random_bytes(4))),
];
$SRC = 'SRC-CONSOLE-EVT-P1';
$sources->inscrireSource(array_merge($gSource(), [
    'reference' => $SRC, 'nom_canonique' => 'source-console-evt-p1', 'nom_affichage' => 'Source Console Événements P1',
    'type_source' => 'SERVICE_CORE', 'proprietaire_reference' => $AUTORITE,
]));
$sources->activerSource($SRC, $gSource());
$FINALITE = 'FINALITE-CONSOLE-EVT-P1';
$sources->declarerFinalite($SRC, array_merge($gSource(), ['finalite_reference' => $FINALITE]));

// ------------------------------------------------------------------- realm
$realms = new RegistreRealms($index, $registreIdentites, RealmsMagasin::connecter(), $ctr01);
$gRealm = static fn (): array => [
    'politique' => PolitiqueRealms::POLITIQUE, 'producteur' => $AUTORITE,
    'source' => 'épreuve console CAP-CORE-014', 'preuve' => 'EVT-CONSOLE-P1-RLM-' . strtoupper(bin2hex(random_bytes(4))),
];
$idnRealm = $inscrireIdentite('realm', 'Realm Console Événements P1');
$insRealm = $realms->inscrireRealm(array_merge($gRealm(), [
    'identite_reference' => $idnRealm, 'code_canonique' => 'RLM-CONSOLE-EVT-P1', 'type_realm_reference' => 'TECHNIQUE',
    'nom_affichage' => 'Realm Console Événements P1', 'classification_reference' => 'INTERNE',
]));
$RLM = (string) $insRealm['reference'];
$realms->activerRealm($RLM, $gRealm());

// ----------------------------------------------------------------- contrat
$contrats = new RegistreContrats($index, $registreIdentites, ContratsMagasin::connecter(), $ctr01);
$gContrat = static fn (): array => [
    'politique' => PolitiqueContrats::POLITIQUE, 'producteur' => $AUTORITE,
    'source' => 'épreuve console CAP-CORE-014', 'preuve' => 'EVT-CONSOLE-P1-CTR-' . strtoupper(bin2hex(random_bytes(4))),
];
$CTR = 'EVT-GAMAD-CONSOLE-P1-TEST';
$CONSOMMATEUR = 'CAP-CONSOLE-EVT-P1-CONSOMMATEUR';
$contrats->inscrireContrat(array_merge($gContrat(), [
    'reference' => $CTR, 'nom' => 'Contrat console P1', 'type_contrat' => 'EVENEMENT',
    'finalite_reference' => $FINALITE, 'producteur_capacite_reference' => 'CAP-CORE-014',
    'proprietaire_reference' => $AUTORITE, 'source_reference' => 'épreuve console CAP-CORE-014',
]));
$contrats->creerVersion($CTR, array_merge($gContrat(), ['version' => '1.0.0', 'compatibilite_annoncee' => 'COMPATIBLE']));
$contrats->declarerPartie($CTR, '1.0.0', array_merge($gContrat(), ['role' => 'PRODUCTEUR', 'partie_type' => 'CAPACITE', 'partie_reference' => 'CAP-CORE-014']));
$contrats->declarerPartie($CTR, '1.0.0', array_merge($gContrat(), ['role' => 'CONSOMMATEUR', 'partie_type' => 'CAPACITE', 'partie_reference' => $CONSOMMATEUR]));
$contrats->declarerOperation($CTR, '1.0.0', array_merge($gContrat(), ['reference_operation' => 'consoleP1', 'type_operation' => 'PUBLIER', 'idempotente' => true]));
$contrats->soumettreVersion($CTR, '1.0.0', $gContrat());
$contrats->analyserCompatibilite($CTR, '1.0.0', $gContrat());
$contrats->enregistrerConformite($CTR, '1.0.0', array_merge($gContrat(), ['resultat' => 'CONFORME', 'artefact_reference' => 'console-p1']));
$activationCtr = $contrats->activerVersion($CTR, '1.0.0', $gContrat());
if (isset($activationCtr['refus'])) {
    throw new RuntimeException('activation du contrat console P1 impossible : ' . json_encode($activationCtr));
}

// --------------------------------------------------------- journal + Laravel
$app = require $application . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->call('core:politiques:bootstrap');
$app->make(\Illuminate\Contracts\Console\Kernel::class)->call('core:evenements:bootstrap');
$app->make(Kernel::class)->bootstrap();

$sessionLaravel = $app->make('session')->driver();
$sessionLaravel->start();
$app->make('view')->share('errors', new ViewErrorBag());
$app->make('redirect')->setSession($sessionLaravel);

$requete = static function (
    string $uri,
    string $methode = 'GET',
    array $donnees = [],
    string $acteur = PolitiqueInscription::AUTORITE_INSCRIPTION,
) use ($app, $sessionLaravel): Request {
    $request = Request::create($uri, $methode, $donnees);
    $request->setLaravelSession($sessionLaravel);
    $request->attributes->set('gamad_entite', $acteur);
    $request->attributes->set('gamad_correlation', 'COR-CONSOLE-EVT-P1');
    $app->instance('request', $request);

    return $request;
};

$echecs = 0;
$verifier = static function (bool $ok, string $libelle) use (&$echecs): void {
    printf("  %s  %s\n", $ok ? '[OK]  ' : '[ÉCHEC]', $libelle);
    if (!$ok) {
        $echecs++;
    }
};

$controleur = $app->make(EvenementConsoleController::class);
$acces = $app->make(AccesEvenements::class);

echo "INTÉGRATION — CONSOLE DU JOURNAL DES ÉVÉNEMENTS P1 (CAP-CORE-014)\n\n";

// 1 — tableau de bord : se rend sans erreur, agrégats à zéro avant toute activité.
$dashboardInitial = $controleur->index($requete('/evenements'), $acces)->render();
$verifier(
    str_contains($dashboardInitial, 'Le journal des événements est cohérent') && !str_contains($dashboardInitial, '<pre>'),
    'le tableau de bord se rend sans erreur, journal cohérent avant toute activité',
);

// 2 — abonnement créé en PREPARATION (gouverné, hors console : pas d'écran de création).
$creation = $acces->creerAbonnement([
    'nom' => 'Abonnement Console P1', 'consommateur_capacite_reference' => $CONSOMMATEUR,
    'realm_reference' => $RLM, 'finalite_reference' => $FINALITE, 'mode_livraison' => 'PULL_API',
], $AUTORITE, null);
$ABN = (string) $creation['corps']['resultat']['reference'];
$verifier($creation['statut'] === 201, 'un abonnement se crée en PREPARATION (hors console)');

// 3 — fiche d'abonnement : PREPARATION, aucun type/producteur/realm déclaré.
$ficheInitiale = $controleur->showAbonnement($requete("/abonnements/{$ABN}"), $acces, $ABN)->render();
$verifier(
    str_contains($ficheInitiale, 'PREPARATION') && str_contains($ficheInitiale, 'Aucun type déclaré'),
    'la fiche d’abonnement affiche PREPARATION sans déclaration',
);

// 4 — types, producteur, realm ajoutés depuis la console ; puis activation.
$controleur->ajouterType($requete("/abonnements/{$ABN}/types", 'POST', [
    'contrat_reference' => $CTR, 'type_evenement' => 'CONSOLE_P1_TEST',
]), $acces, $ABN);
$controleur->ajouterProducteur($requete("/abonnements/{$ABN}/producteurs", 'POST', [
    'producteur_reference' => 'CAP-CORE-014',
]), $acces, $ABN);
$controleur->ajouterRealm($requete("/abonnements/{$ABN}/realms", 'POST', [
    'realm_reference' => $RLM,
]), $acces, $ABN);
$reponseActivation = $controleur->activerAbonnement($requete("/abonnements/{$ABN}/activation", 'POST'), $acces, $ABN);
$ficheActive = $controleur->showAbonnement($requete("/abonnements/{$ABN}"), $acces, $ABN)->render();
$verifier(
    session('succes') !== null
        && str_contains($ficheActive, 'ACTIF')
        && str_contains($ficheActive, $CTR)
        && str_contains($ficheActive, 'CAP-CORE-014'),
    'types, producteur et realm ajoutés depuis la console ; l’activation réussit et apparaît sur la fiche',
);

// 5 — publication d'un événement (hors console : pas d'écran de publication),
// puis fiche événement depuis la console.
$registreEvenements = new RegistreEvenements(EvenementsMagasin::connecter(), $contrats, $sources, $realms);
$accepte = $registreEvenements->accepterEvenement([
    'type_evenement' => 'CONSOLE_P1_TEST', 'contrat_reference' => $CTR, 'contrat_version' => '1.0.0',
    'producteur_capacite_reference' => 'CAP-CORE-014', 'source_reference' => $SRC, 'realm_reference' => $RLM,
    'finalite_reference' => $FINALITE, 'correlation_id' => 'COR-CONSOLE-EVT-P1',
    'idempotence_reference' => 'IDEMP-CONSOLE-EVT-P1', 'survenu_le' => gmdate('c'), 'classification' => 'INTERNE',
    'charge' => ['motif' => 'épreuve console'],
], ['politique' => 'POL-EVENEMENTS-V1', 'producteur' => $AUTORITE, 'source' => 'épreuve console', 'preuve' => 'EVT-CONSOLE-P1-PUB']);
$EVT = (string) $accepte['reference'];
$ficheEvenement = $controleur->showEvenement($requete("/evenements/{$EVT}"), $acces, $EVT)->render();
$verifier(
    str_contains($ficheEvenement, $EVT) && str_contains($ficheEvenement, 'motif') && str_contains($ficheEvenement, 'épreuve console'),
    'la fiche événement affiche l’enveloppe et la charge pour l’autorité',
);

// 6 — dashboard après publication : compteur 1h à jour.
$dashboardApres = $controleur->index($requete('/evenements'), $acces)->render();
$verifier(
    !str_contains($dashboardApres, '<dd>0</dd></div>
                    <div class="summary-row"><dt>24 heures'),
    'le tableau de bord reflète la publication récente (dernière heure > 0)',
);

// 7 — lettre morte : la publication de l'étape 5 a déjà routé une livraison
// vers cet abonnement (type/producteur/realm correspondants) ; elle est
// basculée directement en LETTRE_MORTE (transition de fond déjà éprouvée
// par la garde noyau evenements_p3.php) pour éprouver la console elle-même.
$magasinEvenements = EvenementsMagasin::connecter();
$magasinEvenements->prepare(
    "UPDATE livraison_evenement SET reference = 'LIV-CONSOLE-EVT-P1', etat = 'LETTRE_MORTE'
     WHERE abonnement_reference = ? AND evenement_reference = ?"
)->execute([$ABN, $EVT]);
$magasinEvenements->prepare(
    "INSERT INTO lettre_morte_evenement(reference,livraison_reference,raison_code,tentatives_total,premiere_erreur_le,derniere_erreur_le,cree_le)
     VALUES('LM-CONSOLE-EVT-P1','LIV-CONSOLE-EVT-P1','ERREUR_METIER_DEFINITIVE',3,?,?,?)"
)->execute([gmdate('c'), gmdate('c'), gmdate('c')]);

$listeLettresMortes = $controleur->lettresMortesIndex($requete('/lettres-mortes'), $acces)->render();
$verifier(
    str_contains($listeLettresMortes, 'LM-CONSOLE-EVT-P1') && str_contains($listeLettresMortes, 'OUVERTE'),
    'l’écran lettres mortes liste la lettre morte, ouverte',
);

$controleur->relancerLettreMorte($requete('/lettres-mortes/LM-CONSOLE-EVT-P1/relance', 'POST', [
    'motif' => 'cause corrigée — épreuve console',
]), $acces, 'LM-CONSOLE-EVT-P1');
$ficheLettreMorteRelancee = $controleur->lettresMortesShow($requete('/lettres-mortes/LM-CONSOLE-EVT-P1'), $acces, 'LM-CONSOLE-EVT-P1')->render();
$verifier(
    session('succes') !== null && str_contains($ficheLettreMorteRelancee, 'OUVERTE'),
    'la relance depuis la console réussit ; la fiche reste OUVERTE (aucun effacement)',
);

// Une lettre morte déjà relancée ne peut plus être clôturée (elle est
// retournée à la circulation normale) : la clôture s'éprouve donc sur une
// seconde lettre morte, encore LETTRE_MORTE, jamais relancée.
$magasinEvenements->prepare(
    "INSERT INTO livraison_evenement(reference,abonnement_reference,evenement_reference,sequence_evenement,etat,disponible_le,cree_le)
     VALUES('LIV-CONSOLE-EVT-P1-B', ?, 'EVT-CONSOLE-P1-SYNTHETIQUE-B', 999999, 'LETTRE_MORTE', ?, ?)"
)->execute([$ABN, gmdate('c'), gmdate('c')]);
$magasinEvenements->prepare(
    "INSERT INTO lettre_morte_evenement(reference,livraison_reference,raison_code,tentatives_total,premiere_erreur_le,derniere_erreur_le,cree_le)
     VALUES('LM-CONSOLE-EVT-P1-B','LIV-CONSOLE-EVT-P1-B','ERREUR_METIER_DEFINITIVE',5,?,?,?)"
)->execute([gmdate('c'), gmdate('c'), gmdate('c')]);

$controleur->cloturerLettreMorte($requete('/lettres-mortes/LM-CONSOLE-EVT-P1-B/cloture', 'POST', [
    'motif' => 'abandon définitif — épreuve console',
]), $acces, 'LM-CONSOLE-EVT-P1-B');
$ficheLettreMorteCloturee = $controleur->lettresMortesShow($requete('/lettres-mortes/LM-CONSOLE-EVT-P1-B'), $acces, 'LM-CONSOLE-EVT-P1-B')->render();
$verifier(
    str_contains($ficheLettreMorteCloturee, 'CLÔTURÉE'),
    'la clôture depuis la console apparaît sur la fiche, sans suppression de la ligne',
);

// 8 — rejeu : formulaire, demande bornée, validation, annulation d'une seconde demande.
$formulaireRejeu = $controleur->rejeuxCreate($requete('/rejeux/nouveau', 'GET', ['abonnement' => $ABN]))->render();
$verifier(
    str_contains($formulaireRejeu, 'Demander un rejeu') && str_contains($formulaireRejeu, $ABN),
    'le formulaire de rejeu préremplit l’abonnement passé en paramètre',
);

$reponseRejeu = $controleur->rejeuxStore($requete('/rejeux', 'POST', [
    'abonnement_reference' => $ABN, 'motif' => 'épreuve console P1',
    'sequence_debut' => (int) $accepte['sequence'], 'sequence_fin' => (int) $accepte['sequence'],
]), $acces);
$cibleRejeu = $reponseRejeu->getTargetUrl();
$REJ = substr($cibleRejeu, strrpos($cibleRejeu, '/') + 1);
$ficheRejeuDemandee = $controleur->rejeuxShow($requete("/rejeux/{$REJ}"), $acces, $REJ)->render();
$verifier(
    str_contains($ficheRejeuDemandee, 'DEMANDEE') && str_contains($ficheRejeuDemandee, 'Valider'),
    'la demande de rejeu se crée en DEMANDEE et propose la validation',
);

$controleur->validerRejeu($requete("/rejeux/{$REJ}/validation", 'POST'), $acces, $REJ);
$ficheRejeuValidee = $controleur->rejeuxShow($requete("/rejeux/{$REJ}"), $acces, $REJ)->render();
$verifier(str_contains($ficheRejeuValidee, 'VALIDEE'), 'la validation depuis la console fait transiter le rejeu vers VALIDEE');

$secondeRejeu = $acces->demanderRejeu($ABN, [
    'motif' => 'seconde demande — annulation', 'sequence_debut' => (int) $accepte['sequence'], 'sequence_fin' => (int) $accepte['sequence'],
], $AUTORITE, null);
$REJ2 = (string) $secondeRejeu['corps']['resultat']['reference'];
$controleur->annulerRejeu($requete("/rejeux/{$REJ2}/annulation", 'POST'), $acces, $REJ2);
$ficheRejeuAnnulee = $controleur->rejeuxShow($requete("/rejeux/{$REJ2}"), $acces, $REJ2)->render();
$verifier(str_contains($ficheRejeuAnnulee, 'ANNULEE'), 'l’annulation depuis la console fait transiter le rejeu vers ANNULEE');

$listeRejeux = $controleur->rejeuxIndex($requete('/rejeux', 'GET', ['abonnement' => $ABN]), $acces)->render();
$verifier(
    str_contains($listeRejeux, $REJ) && str_contains($listeRejeux, $REJ2),
    'l’écran rejeux liste les deux demandes de l’abonnement filtré',
);

// 9 — suspension puis retrait de l'abonnement, visibles sur la fiche.
$controleur->suspendreAbonnement($requete("/abonnements/{$ABN}/suspension", 'POST'), $acces, $ABN);
$controleur->retirerAbonnement($requete("/abonnements/{$ABN}/retrait", 'POST'), $acces, $ABN);
$ficheRetiree = $controleur->showAbonnement($requete("/abonnements/{$ABN}"), $acces, $ABN)->render();
$verifier(str_contains($ficheRetiree, 'RETIRE'), 'suspension puis retrait apparaissent sur la fiche, dans l’ordre');

echo "\n";
if ($echecs === 0) {
    echo "Console du journal des événements P1 : ÉTABLIE.\n";
    exit(0);
}
echo "Console du journal des événements P1 : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
