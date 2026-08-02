<?php

declare(strict_types=1);

/**
 * Garde de comportement de CAP-CORE-022 — fédération des satellites.
 *
 * Le pilote est GamaDrive (`PRD-GAMAD-002`), seul produit dont l'état dérivé
 * porte la reconnaissance. Wasplex (`PRD-GAMAD-003`) sert de contre-épreuve
 * permanente : ni fédérable, ni destinataire d'un jeton qui ne lui est pas
 * adressé.
 *
 * Exécution : php core/registre-federation/tests/federation_p3.php
 * Code de sortie : 0 si toutes les épreuves et contre-épreuves passent.
 */

use Gamad\RegistreAcces\Ctr16;
use Gamad\RegistreAcces\Magasin as AccesMagasin;
use Gamad\RegistreAutorisation\Ctr03;
use Gamad\RegistreFederation\Federation;
use Gamad\RegistreFederation\PolitiqueFederation;
use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreIdentites\PolitiqueInscription;
use Gamad\RegistreNormes\BaselineOperationnelle;
use Gamad\RegistreNormes\Db;
use Gamad\RegistreProduits\Magasin as ProduitsMagasin;
use Gamad\RegistreProduits\PolitiqueProduits;
use Gamad\RegistreProduits\RegistreProduits;

require __DIR__ . '/../../registre-normes/bootstrap.php';
require __DIR__ . '/../../registre-identites/src/PolitiqueInscription.php';
require __DIR__ . '/../../registre-identites/src/SchemaInscription.php';
require __DIR__ . '/../../registre-identites/src/Magasin.php';
require __DIR__ . '/../../registre-identites/src/Ctr01.php';
require __DIR__ . '/../../registre-acces/src/Magasin.php';
require __DIR__ . '/../../registre-acces/src/Ctr16.php';
require __DIR__ . '/../../registre-autorisation/src/Ctr03.php';
require __DIR__ . '/../../registre-produits/src/PolitiqueProduits.php';
require __DIR__ . '/../../registre-produits/src/SchemaProduits.php';
require __DIR__ . '/../../registre-produits/src/Magasin.php';
require __DIR__ . '/../../registre-produits/src/RegistreProduits.php';
require __DIR__ . '/../src/PolitiqueFederation.php';
require __DIR__ . '/../src/SchemaFederation.php';
require __DIR__ . '/../src/Federation.php';

$prefixe = sys_get_temp_dir() . '/gamad-federation-' . getmypid();
$fichiers = [
    'index' => $prefixe . '-index.sqlite',
    'identites' => $prefixe . '-identites.sqlite',
    'acces' => $prefixe . '-acces.sqlite',
    'produits' => $prefixe . '-produits.sqlite',
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

$registre = IdentiteMagasin::connecter($fichiers['identites']);
$magasin = AccesMagasin::connecter($fichiers['acces']);
$magasinProduits = ProduitsMagasin::connecter($fichiers['produits']);
$ctr01 = new Ctr01($index, $registre);
$produits = new RegistreProduits($index, $registre, $magasinProduits, $ctr01);
$federation = new Federation($index, $registre, $magasin, $magasinProduits, $ctr01, $produits);
$ctr16 = new Ctr16($magasin);

// CAP-CORE-011 en écriture gouvernée : le pilote GamaDrive est inscrit puis
// activé avec sa fédération explicitement autorisée, reproduisant l'état
// RECONNU déjà porté par la baseline documentaire. Wasplex reste en
// PREPARATION, non fédérable, comme le bootstrap de production le fait.
$dossierProduit = static fn (array $extra = []): array => $extra + [
    'politique' => PolitiqueProduits::POLITIQUE,
    'source' => PolitiqueProduits::SOURCE,
    'producteur' => PolitiqueInscription::AUTORITE_INSCRIPTION,
    'preuve' => 'EVT-P3-PRD-' . strtoupper(bin2hex(random_bytes(4))),
];
foreach (['PRD-GAMAD-002' => 'GamaDrive', 'PRD-GAMAD-003' => 'Wasplex'] as $ref => $libelle) {
    $produits->inscrireProduit($dossierProduit([
        'reference' => $ref, 'identite_reference' => $ref,
        'nom_canonique' => $libelle, 'nom_affichage' => $libelle,
        'type_produit' => $ref === 'PRD-GAMAD-002' ? 'SATELLITE' : 'PARTENAIRE',
        'proprietaire_reference' => PolitiqueInscription::AUTORITE_INSCRIPTION,
    ]));
}
$produits->modifierProduit('PRD-GAMAD-002', $dossierProduit(['federation_autorisee' => true]));
$produits->activerProduit('PRD-GAMAD-002', $dossierProduit());

$echecs = 0;
echo "GARDE — FÉDÉRATION DES SATELLITES (CAP-CORE-022)\n\n";

$verifier = static function (bool $ok, string $message) use (&$echecs): void {
    echo $ok ? "  [OK]    {$message}\n" : "  [ÉCHEC] {$message}\n";
    if (!$ok) {
        $echecs++;
    }
};

$DRIVE = 'PRD-GAMAD-002';
$WASPLEX = 'PRD-GAMAD-003';

$inscrire = static function (string $libelle, bool $provisoire = false) use ($ctr01): string {
    $identite = $ctr01->inscrireIdentite([
        'canal' => $provisoire ? 'AUTO_INSCRIPTION' : 'AUTORITE',
        'type' => 'personne',
        'libelle' => $libelle,
        'producteur' => PolitiqueInscription::AUTORITE_INSCRIPTION,
        'politique' => 'POL-FEDERATION-P3',
        'source' => 'garde CAP-CORE-022',
        'preuve' => 'EVT-P3-FED-' . strtoupper(bin2hex(random_bytes(4))),
        'provisoire' => $provisoire,
    ]);
    if (isset($identite['refus'])) {
        throw new RuntimeException('inscription impossible : ' . json_encode($identite));
    }

    return (string) $identite['reference'];
};

$ouvrirSession = static function (string $identite) use ($ctr16): string {
    $secret = 'Secret-Federation-P3-' . bin2hex(random_bytes(4));
    $ctr16->inscrireAuthentificateur($identite, $secret);
    $session = $ctr16->etablirSession($identite, $secret);
    if ($session === null) {
        throw new RuntimeException('session impossible pour ' . $identite);
    }

    return (string) $session['session'];
};

$dossier = static fn (string $session, array $extra = []): array => [
    'politique' => PolitiqueFederation::POLITIQUE,
    'source' => PolitiqueFederation::SOURCE,
    'preuve' => 'EVT-P3-FED-PREUVE-' . strtoupper(bin2hex(random_bytes(4))),
    'session_empreinte' => Federation::empreinteSession($session),
] + $extra;

$porteur = $inscrire('Porteur fédéré P3');
$tiers = $inscrire('Tiers fédéré P3');
$provisoire = $inscrire('Identité provisoire fédérée P3', true);
$session = $ouvrirSession($porteur);

// 1 — le catalogue distingue le produit reconnu du partenaire non entériné.
$catalogue = $federation->catalogueProduits();
$parReference = array_column($catalogue, null, 'reference');
$verifier(
    count($catalogue) === 2
        && ($parReference[$DRIVE]['federable'] ?? null) === true
        && ($parReference[$WASPLEX]['federable'] ?? null) === false,
    'le catalogue des produits distingue les satellites fédérables',
);

// 2 — la politique technique est portée par l'index, pas par le code.
$ctr03 = new Ctr03($index);
$permise = $ctr03->simuler($porteur, PolitiqueFederation::ACTION_OUVERTURE, $DRIVE);
$inconnue = $ctr03->simuler($porteur, 'ouvrir un satellite sans politique', $DRIVE);
$verifier(
    ($permise['decision'] ?? null) === 'PERMIS'
        && ($permise['politique'] ?? null) === PolitiqueFederation::POLITIQUE
        && ($inconnue['decision'] ?? null) === 'REFUSÉ',
    'CAP-CORE-004 permet l’ouverture fédérée et refuse toujours par défaut',
);

// 3 — un partenaire non entériné n'est pas fédérable.
$refusWasplex = $federation->ouvrir($porteur, $WASPLEX, $porteur, $dossier($session));
$verifier(
    ($refusWasplex['refus'] ?? null) === 'PRODUIT_NON_RECONNU',
    'un produit non reconnu ne reçoit aucun accès fédéré',
);

// 4 — ouverture du pilote : lien produit provisionné et jeton borné.
$premiere = $federation->ouvrir($porteur, $DRIVE, $porteur, $dossier($session, [
    'sujet_local_opaque' => 'drive-sujet-p3-01',
]));
$verifier(
    str_starts_with((string) ($premiere['reference'] ?? ''), 'JFD-')
        && str_starts_with((string) ($premiere['jeton'] ?? ''), 'FED-')
        && ($premiere['audience'] ?? null) === $DRIVE
        && str_starts_with((string) ($premiere['relation'] ?? ''), 'LNK-PRD-')
        && ($premiere['portees'] ?? null) === PolitiqueFederation::PORTEES
        && ($premiere['provisionne'] ?? null) === true
        && ($premiere['expire_le'] ?? '') > ($premiere['emis_le'] ?? ''),
    'l’ouverture du pilote provisionne un lien produit et émet un jeton borné',
);

// 5 — provisionnement idempotent : rejouer n'ouvre pas un second compte local.
$seconde = $federation->ouvrir($porteur, $DRIVE, $porteur, $dossier($session));
$liens = (int) $registre->query(
    "SELECT count(*) FROM relation_produit WHERE produit_reference = '{$DRIVE}'"
)->fetchColumn();
$verifier(
    ($seconde['relation'] ?? null) === ($premiere['relation'] ?? null)
        && ($seconde['provisionne'] ?? null) === false
        && $liens === 1,
    'répéter l’ouverture ne crée pas un second lien produit',
);

// 6 — un jeton destiné à GamaDrive n'est pas utilisable par Wasplex, et la
// tentative ne le consomme pas au préjudice de son destinataire.
$etrangere = $federation->verifierJeton((string) $seconde['jeton'], $WASPLEX);
$legitime = $federation->verifierJeton((string) $seconde['jeton'], $DRIVE);
$verifier(
    ($etrangere['valide'] ?? null) === false
        && ($etrangere['motif'] ?? null) === 'AUDIENCE_ETRANGERE'
        && ($legitime['valide'] ?? null) === true
        && ($legitime['identite'] ?? null) === $porteur
        && ($legitime['portees'] ?? null) === PolitiqueFederation::PORTEES,
    'un jeton n’est présentable qu’au satellite auquel il est destiné',
);

// 7 — usage unique : un jeton consommé ne se rejoue pas.
$rejeu = $federation->verifierJeton((string) $seconde['jeton'], $DRIVE);
$verifier(
    ($rejeu['valide'] ?? null) === false
        && ($rejeu['motif'] ?? null) === 'JETON_DEJA_CONSOMME',
    'un jeton fédéré consommé n’est jamais rejouable',
);

// 8 — la durée est bornée, et l'expiration ferme le jeton.
$horsBornes = $federation->ouvrir($porteur, $DRIVE, $porteur, $dossier($session, [
    'duree' => PolitiqueFederation::DUREE_MAXIMALE + 1,
]));
$court = $federation->ouvrir($porteur, $DRIVE, $porteur, $dossier($session, [
    'duree' => PolitiqueFederation::DUREE_MINIMALE,
]));
$expire = $federation->verifierJeton(
    (string) $court['jeton'],
    $DRIVE,
    date('c', time() + PolitiqueFederation::DUREE_MINIMALE + 1),
);
$verifier(
    ($horsBornes['refus'] ?? null) === 'DUREE_HORS_LIMITES'
        && ($expire['valide'] ?? null) === false
        && ($expire['motif'] ?? null) === 'JETON_EXPIRE',
    'aucun jeton fédéré n’est de longue vie et l’expiration est opposable',
);

// 9 — la session Core fermée emporte les jetons qu'elle a produits, que la
// fermeture explicite ait été exécutée ou non.
$avantDeconnexion = $federation->ouvrir($porteur, $DRIVE, $porteur, $dossier($session));
$second = $federation->ouvrir($porteur, $DRIVE, $porteur, $dossier($session));
$ctr16->revoquerSession($session);
$apresRevocationSession = $federation->verifierJeton((string) $avantDeconnexion['jeton'], $DRIVE);
$fermes = $federation->revoquerJetonsDeSession(
    Federation::empreinteSession($session),
    'session Core fermée',
);
$apresFermeture = $federation->verifierJeton((string) $second['jeton'], $DRIVE);
$verifier(
    ($apresRevocationSession['motif'] ?? null) === 'SESSION_CORE_FERMEE'
        && $fermes >= 2
        && ($apresFermeture['motif'] ?? null) === 'JETON_REVOQUE',
    'la déconnexion globale ferme les jetons fédérés de la session',
);

// 10 — révocation d'un accès produit : le lien est clos, les jetons tombent,
// l'identité demeure.
$session = $ouvrirSession($porteur);
$avantRevocation = $federation->ouvrir($porteur, $DRIVE, $porteur, $dossier($session));
$revocation = $federation->revoquerAcces($porteur, $DRIVE, $porteur, [
    'politique' => PolitiqueFederation::POLITIQUE,
    'source' => PolitiqueFederation::SOURCE,
    'preuve' => 'EVT-P3-FED-REVOCATION',
]);
$apresRevocation = $federation->verifierJeton((string) $avantRevocation['jeton'], $DRIVE);
$verifier(
    ($revocation['relation_etat'] ?? null) === 'CLOSE'
        && ($revocation['jetons_fermes'] ?? 0) >= 1
        && ($apresRevocation['motif'] ?? null) === 'JETON_REVOQUE'
        && ($ctr01->resoudreIdentite($porteur)['etat'] ?? null) === 'ACTIVE',
    'révoquer un accès ferme le lien et les jetons sans toucher l’identité',
);

// 11 — reprise contrôlée : une nouvelle ouverture rétablit un lien distinct.
$reprise = $federation->ouvrir($porteur, $DRIVE, $porteur, $dossier($session));
$verifier(
    ($reprise['provisionne'] ?? null) === true
        && ($reprise['relation'] ?? null) !== ($avantRevocation['relation'] ?? null),
    'la reprise après révocation ouvre un lien nouveau, jamais l’ancien',
);

// 12 — un tiers n'ouvre pas l'accès d'autrui ; l'autorité le peut.
$sessionAutorite = $ouvrirSession(PolitiqueInscription::AUTORITE_INSCRIPTION);
$parTiers = $federation->ouvrir($porteur, $DRIVE, $tiers, $dossier($session));
$parAutorite = $federation->ouvrir(
    $tiers,
    $DRIVE,
    PolitiqueInscription::AUTORITE_INSCRIPTION,
    $dossier($sessionAutorite),
);
$verifier(
    ($parTiers['refus'] ?? null) === 'ACTEUR_INCOMPETENT'
        && isset($parAutorite['jeton']),
    'un accès fédéré s’ouvre pour soi-même ou par l’autorité d’inscription',
);

// 13 — une identité provisoire A0 n'ouvre aucun satellite.
$refusProvisoire = $federation->ouvrir($provisoire, $DRIVE, $provisoire, $dossier($session));
$verifier(
    ($refusProvisoire['refus'] ?? null) === 'IDENTITE_NON_UTILISABLE',
    'une identité provisoire ou A0 n’ouvre aucun accès fédéré',
);

// 14 — la vue transversale des porteurs reste bornée : le satellite concerné
// et l'autorité la lisent, personne d'autre.
$vueAutorite = $federation->resoudrePorteurs(
    $DRIVE,
    PolitiqueInscription::AUTORITE_INSCRIPTION,
);
$vueSatellite = $federation->resoudrePorteurs($DRIVE, $DRIVE);
$vueEtrangere = $federation->resoudrePorteurs($DRIVE, $WASPLEX);
$vuePorteur = $federation->resoudrePorteurs($DRIVE, $porteur);
$referencesVues = array_column($vueAutorite['porteurs'] ?? [], 'identite');
$verifier(
    in_array($porteur, $referencesVues, true)
        && in_array($tiers, $referencesVues, true)
        && count($vueSatellite['porteurs'] ?? []) === count($referencesVues)
        && ($vueEtrangere['refus'] ?? null) === 'ACTEUR_INCOMPETENT'
        && ($vuePorteur['refus'] ?? null) === 'ACTEUR_INCOMPETENT',
    'la liste des porteurs n’est lisible que par le satellite concerné ou l’autorité',
);

// 15 — aucun jeton en clair dans le magasin, seulement son empreinte.
$contenu = (string) file_get_contents($fichiers['acces']);
$empreinte = hash('sha256', (string) $reprise['jeton']);
$verifier(
    !str_contains($contenu, (string) $reprise['jeton'])
        && str_contains($contenu, $empreinte),
    'le magasin ne conserve que l’empreinte du jeton, jamais sa valeur',
);

// 16 — le schéma ne porte ni profil, ni jugement, ni donnée métier du satellite.
$interdites = ['profil', 'reputation', 'réputation', 'jugement', 'plan', 'quota', 'abonnement'];
$colonnesInterdites = [];
foreach ($magasin->query('PRAGMA table_info(jeton_federe)')->fetchAll() as $colonne) {
    $nom = mb_strtolower((string) $colonne['name'], 'UTF-8');
    foreach ($interdites as $interdite) {
        if (str_contains($nom, $interdite)) {
            $colonnesInterdites[] = "jeton_federe.{$nom}";
        }
    }
}
$verifier(
    $colonnesInterdites === [],
    'le schéma des jetons ne porte aucune donnée économique ni aucun jugement',
);

// 17 — le parcours HTTP existe et reste gouverné.
$routesApi = (string) file_get_contents(GAMAD_RACINE . '/apps/console-laravel/routes/api.php');
$casUsage = (string) file_get_contents(
    GAMAD_RACINE . '/apps/console-laravel/app/Application/Federation/AccesSatellites.php'
);
$verifier(
    str_contains($routesApi, "Route::post('/produits/{produit}/ouverture'")
        && str_contains($routesApi, "Route::post('/produits/{produit}/verification'")
        && str_contains($routesApi, "Route::post('/produits/{produit}/revocation'")
        && str_contains($routesApi, "Route::middleware('gamad.api')")
        && str_contains($casUsage, '->autoriser(')
        && str_contains($casUsage, 'enregistrer('),
    'l’API v1 de fédération exige une session, une décision et une preuve',
);

echo "\n";
if ($echecs === 0) {
    echo "Garde CAP-CORE-022 : ÉTABLIE.\n";
    exit(0);
}
echo "Garde CAP-CORE-022 : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
