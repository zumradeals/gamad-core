<?php

declare(strict_types=1);

/**
 * Garde de comportement de CAP-CORE-011 — registre des produits.
 *
 * Exécution : php core/registre-produits/tests/produits_p3.php
 * Code de sortie : 0 si toutes les épreuves et contre-épreuves passent.
 */

use Gamad\RegistreAutorisation\Ctr03;
use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreIdentites\PolitiqueInscription;
use Gamad\RegistreNormes\BaselineOperationnelle;
use Gamad\RegistreNormes\Db;
use Gamad\RegistrePolitiques\Magasin as PolitiquesMagasin;
use Gamad\RegistrePolitiques\PolitiqueAdministration;
use Gamad\RegistrePolitiques\RegistrePolitiques;
use Gamad\RegistreProduits\Magasin as ProduitsMagasin;
use Gamad\RegistreProduits\PolitiqueProduits;
use Gamad\RegistreProduits\RegistreProduits;

require __DIR__ . '/../../registre-normes/bootstrap.php';
require __DIR__ . '/../../registre-identites/src/PolitiqueInscription.php';
require __DIR__ . '/../../registre-identites/src/SchemaInscription.php';
require __DIR__ . '/../../registre-identites/src/Magasin.php';
require __DIR__ . '/../../registre-identites/src/Ctr01.php';
require __DIR__ . '/../../registre-autorisation/src/Ctr03.php';
require __DIR__ . '/../../registre-politiques/src/PolitiqueAdministration.php';
require __DIR__ . '/../../registre-politiques/src/SchemaPolitiques.php';
require __DIR__ . '/../../registre-politiques/src/Magasin.php';
require __DIR__ . '/../../registre-politiques/src/RegistrePolitiques.php';
require __DIR__ . '/../src/PolitiqueProduits.php';
require __DIR__ . '/../src/SchemaProduits.php';
require __DIR__ . '/../src/Magasin.php';
require __DIR__ . '/../src/RegistreProduits.php';

$prefixe = sys_get_temp_dir() . '/gamad-produits-' . getmypid();
$fichiers = [
    'index' => $prefixe . '-index.sqlite',
    'identites' => $prefixe . '-identites.sqlite',
    'produits' => $prefixe . '-produits.sqlite',
    'politiques' => $prefixe . '-politiques.sqlite',
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
$magasinProduits = ProduitsMagasin::connecter($fichiers['produits']);
$ctr01 = new Ctr01($index, $registreIdentites);
$registre = new RegistreProduits($index, $registreIdentites, $magasinProduits, $ctr01);

// CAP-CORE-004 lit désormais le registre persistant des politiques
// (CAP-CORE-007), plus jamais l'index. Reprise fidèle de POL-PRODUITS-V1
// depuis la photographie figée du bootstrap — même mécanisme que
// `core:politiques:bootstrap` — pour que CTR-03 dispose d'une version ACTIVE
// réelle à évaluer plus bas.
$magasinPolitiques = PolitiquesMagasin::connecter($fichiers['politiques']);
$registrePolitiques = new RegistrePolitiques($index, $registreIdentites, $magasinPolitiques, $ctr01);
(static function () use ($registrePolitiques): void {
    $g = static fn (): array => [
        'politique' => PolitiqueAdministration::POLITIQUE, 'producteur' => 'AUT-GAMAD-001',
        'source' => 'garde CAP-CORE-011', 'preuve' => 'P-' . bin2hex(random_bytes(4)),
    ];
    $bootstrap = json_decode(
        file_get_contents(__DIR__ . '/../../registre-politiques/resources/bootstrap-politiques-v1.json'),
        true,
    );
    $p = null;
    foreach ($bootstrap['politiques'] as $ligne) {
        if ($ligne['reference'] === 'POL-PRODUITS-V1') {
            $p = $ligne;
            break;
        }
    }
    $version = $p['version'];
    $sourceRef = $p['source'] . (!empty($p['adoption_reference']) ? ' (' . $p['adoption_reference'] . ')' : '');
    $registrePolitiques->inscrirePolitique(array_merge($g(), [
        'reference' => $p['reference'], 'libelle' => $p['libelle'],
        'proprietaire_reference' => 'AUT-GAMAD-001', 'source_reference' => $sourceRef,
    ]));
    $registrePolitiques->creerVersion($p['reference'], array_merge($g(), ['version' => $version]));
    $regles = $p['regles'];
    $cas = [];
    foreach ($regles as $r) {
        $registrePolitiques->ajouterRegle($p['reference'], $version, array_merge($g(), [
            'effet' => $r['effet'], 'action_reference' => $r['action'],
            'sujet_reference' => $r['sujet_type'], 'motif' => $r['motif'],
        ]));
        $cas[] = ['sujet' => $r['sujet_type'] ?? 'AUT-GAMAD-001', 'action' => $r['action'], 'attendu' => $r['effet'] === 'PERMET' ? 'PERMIS' : 'REFUSE'];
    }
    $registrePolitiques->soumettreVersion($p['reference'], $version, $g());
    $registrePolitiques->simulerVersion($p['reference'], $version, array_merge($g(), ['jeu_reference' => 'GARDE', 'cas' => $cas]));
    $registrePolitiques->activerVersion($p['reference'], $version, $g());
})();

$echecs = 0;
echo "GARDE — REGISTRE DES PRODUITS (CAP-CORE-011)\n\n";

$verifier = static function (bool $ok, string $message) use (&$echecs): void {
    echo $ok ? "  [OK]    {$message}\n" : "  [ÉCHEC] {$message}\n";
    if (!$ok) {
        $echecs++;
    }
};

$inscrireProduitIdentite = static function (string $reference, string $libelle) use ($ctr01): string {
    $identite = $ctr01->inscrireIdentite([
        'canal' => 'CREATION_TECHNIQUE',
        'type' => 'produit',
        'libelle' => $libelle,
        'producteur' => PolitiqueInscription::AUTORITE_INSCRIPTION,
        'politique' => 'POL-PRODUITS-P3',
        'source' => 'garde CAP-CORE-011',
        'preuve' => 'EVT-P3-PRD-' . strtoupper(bin2hex(random_bytes(4))),
    ]);
    if (isset($identite['refus'])) {
        throw new RuntimeException('inscription identité produit impossible : ' . json_encode($identite));
    }

    return $reference;
};

$dossier = static fn (array $extra = []): array => $extra + [
    'politique' => PolitiqueProduits::POLITIQUE,
    'source' => PolitiqueProduits::SOURCE,
    'producteur' => PolitiqueProduits::AUTORITE,
    'preuve' => 'EVT-P3-PRD-PREUVE-' . strtoupper(bin2hex(random_bytes(4))),
];

// Deux identités de type `produit`, inscrites au registre d'identités (régime
// INSCRIT), pour ne pas dépendre des quatre entités déjà présentes dans la
// baseline documentaire.
$refPilote = 'PRD-P3-' . strtoupper(bin2hex(random_bytes(4)));
$refSecond = 'PRD-P3-' . strtoupper(bin2hex(random_bytes(4)));
$identitePilote = $ctr01->inscrireIdentite([
    'canal' => 'CREATION_TECHNIQUE', 'type' => 'produit', 'libelle' => 'Pilote P3',
    'producteur' => PolitiqueInscription::AUTORITE_INSCRIPTION, 'politique' => 'POL-PRODUITS-P3',
    'source' => 'garde CAP-CORE-011', 'preuve' => 'EVT-P3-IDN-' . strtoupper(bin2hex(random_bytes(4))),
]);
$identiteSeconde = $ctr01->inscrireIdentite([
    'canal' => 'CREATION_TECHNIQUE', 'type' => 'produit', 'libelle' => 'Second P3',
    'producteur' => PolitiqueInscription::AUTORITE_INSCRIPTION, 'politique' => 'POL-PRODUITS-P3',
    'source' => 'garde CAP-CORE-011', 'preuve' => 'EVT-P3-IDN-' . strtoupper(bin2hex(random_bytes(4))),
]);
$identiteHumaine = $ctr01->inscrireIdentite([
    'canal' => 'AUTORITE', 'type' => 'personne', 'libelle' => 'Propriétaire P3',
    'producteur' => PolitiqueInscription::AUTORITE_INSCRIPTION, 'politique' => 'POL-PRODUITS-P3',
    'source' => 'garde CAP-CORE-011', 'preuve' => 'EVT-P3-IDN-' . strtoupper(bin2hex(random_bytes(4))),
]);
$IDN_PILOTE = (string) $identitePilote['reference'];
$IDN_SECOND = (string) $identiteSeconde['reference'];
$PROPRIETAIRE = (string) $identiteHumaine['reference'];

// 1, 3 — inscription gouvernée, refus sans politique / sans preuve.
$sansPolitique = $registre->inscrireProduit($dossier([
    'reference' => $refPilote, 'identite_reference' => $IDN_PILOTE,
    'nom_canonique' => 'Pilote P3', 'nom_affichage' => 'Pilote P3',
    'type_produit' => 'SATELLITE', 'proprietaire_reference' => $PROPRIETAIRE,
    'politique' => '',
]));
$sansPreuve = $registre->inscrireProduit($dossier([
    'reference' => $refPilote, 'identite_reference' => $IDN_PILOTE,
    'nom_canonique' => 'Pilote P3', 'nom_affichage' => 'Pilote P3',
    'type_produit' => 'SATELLITE', 'proprietaire_reference' => $PROPRIETAIRE,
    'preuve' => '',
]));
$inscription = $registre->inscrireProduit($dossier([
    'reference' => $refPilote, 'identite_reference' => $IDN_PILOTE,
    'nom_canonique' => 'Pilote P3', 'nom_affichage' => 'Pilote P3',
    'type_produit' => 'SATELLITE', 'proprietaire_reference' => $PROPRIETAIRE,
]));
$verifier(
    ($sansPolitique['refus'] ?? null) === 'DOSSIER_INCOMPLET'
        && ($sansPreuve['refus'] ?? null) === 'DOSSIER_INCOMPLET'
        && ($inscription['etat'] ?? null) === 'PREPARATION'
        && ($inscription['reference'] ?? null) === $refPilote,
    'l’inscription exige politique et preuve, et crée le produit en PREPARATION',
);

// 4 — refus d'une référence dupliquée.
$doublon = $registre->inscrireProduit($dossier([
    'reference' => $refPilote, 'identite_reference' => $IDN_SECOND,
    'nom_canonique' => 'Doublon', 'nom_affichage' => 'Doublon',
    'type_produit' => 'SATELLITE', 'proprietaire_reference' => $PROPRIETAIRE,
]));
$verifier(
    ($doublon['refus'] ?? null) === 'REFERENCE_DEJA_UTILISEE',
    'une référence déjà inscrite est refusée',
);

// 5 — refus d'une identité produit déjà liée à un autre produit.
$identiteLiee = $registre->inscrireProduit($dossier([
    'reference' => $refSecond, 'identite_reference' => $IDN_PILOTE,
    'nom_canonique' => 'Second', 'nom_affichage' => 'Second',
    'type_produit' => 'SATELLITE', 'proprietaire_reference' => $PROPRIETAIRE,
]));
$verifier(
    ($identiteLiee['refus'] ?? null) === 'IDENTITE_DEJA_LIEE',
    'une identité canonique déjà attachée à un produit ne se réattache pas',
);

// Contre-épreuves d'inscription : identité inconnue, identité non-produit.
$identiteInconnue = $registre->inscrireProduit($dossier([
    'reference' => $refSecond, 'identite_reference' => 'IDN-PRD-INEXISTANTE',
    'nom_canonique' => 'X', 'nom_affichage' => 'X',
    'type_produit' => 'SATELLITE', 'proprietaire_reference' => $PROPRIETAIRE,
]));
$identiteNonProduit = $registre->inscrireProduit($dossier([
    'reference' => $refSecond, 'identite_reference' => $PROPRIETAIRE,
    'nom_canonique' => 'X', 'nom_affichage' => 'X',
    'type_produit' => 'SATELLITE', 'proprietaire_reference' => $PROPRIETAIRE,
]));
$verifier(
    ($identiteInconnue['refus'] ?? null) === 'IDENTITE_INCONNUE'
        && ($identiteNonProduit['refus'] ?? null) === 'IDENTITE_TYPE_INVALIDE',
    'une identité canonique inconnue ou d’un autre type ne peut pas porter un produit',
);

// 7, 8 — activation autorisée, refus d'auto-activation.
$autoActivation = $registre->activerProduit($refPilote, $dossier(['producteur' => $refPilote]));
$activation = $registre->activerProduit($refPilote, $dossier());
$reactivationIdempotente = $registre->activerProduit($refPilote, $dossier());
$verifier(
    ($autoActivation['refus'] ?? null) === 'AUTO_ACTIVATION_INTERDITE'
        && ($activation['etat'] ?? null) === 'ACTIF'
        && ($activation['idempotent'] ?? null) === false
        && ($reactivationIdempotente['idempotent'] ?? null) === true,
    'un produit ne s’auto-active jamais ; l’activation gouvernée est idempotente',
);

// 2 — refus par défaut : CAP-CORE-004 refuse toute action hors politique.
$ctr03 = new Ctr03($magasinPolitiques);
$permise = $ctr03->simuler(PolitiqueProduits::AUTORITE, PolitiqueProduits::ACTION_ACTIVER, $refPilote);
$inconnue = $ctr03->simuler(PolitiqueProduits::AUTORITE, 'activer un produit sans aucune politique adoptée', $refPilote);
$verifier(
    ($permise['decision'] ?? null) === 'PERMIS'
        && ($permise['politique'] ?? null) === PolitiqueProduits::POLITIQUE
        && ($inconnue['decision'] ?? null) === 'REFUSÉ',
    'CAP-CORE-004 permet l’activation gouvernée et refuse toujours par défaut',
);

// 9, 19 — suspension immédiatement opposable, produit suspendu non fédérable.
$registre->modifierProduit($refPilote, $dossier(['federation_autorisee' => true]));
$federableAvant = $registre->verifierUtilisablePourFederation($refPilote);
$suspension = $registre->suspendreProduit($refPilote, $dossier());
$federableApres = $registre->verifierUtilisablePourFederation($refPilote);
$verifier(
    $federableAvant['utilisable'] === true
        && ($suspension['etat'] ?? null) === 'SUSPENDU'
        && $federableApres['utilisable'] === false
        && $federableApres['motif'] === 'PRODUIT_NON_ACTIF',
    'la suspension retire immédiatement la fédérabilité',
);

// Reprise, puis 10, 20 — retrait irréversible sans suppression, non fédérable.
$registre->activerProduit($refPilote, $dossier());
$retrait = $registre->retirerProduit($refPilote, $dossier());
$retraitRejoue = $registre->retirerProduit($refPilote, $dossier());
$activationApresRetrait = $registre->activerProduit($refPilote, $dossier());
$toujoursLisible = $registre->resoudreProduit($refPilote);
$federableRetire = $registre->verifierUtilisablePourFederation($refPilote);
$verifier(
    ($retrait['etat'] ?? null) === 'RETIRE'
        && ($retraitRejoue['idempotent'] ?? null) === true
        && ($activationApresRetrait['refus'] ?? null) === 'ETAT_INCOMPATIBLE'
        && $toujoursLisible !== null
        && $toujoursLisible['etat'] === 'RETIRE'
        && $federableRetire['utilisable'] === false,
    'le retrait est irréversible, ne supprime rien, et ferme la fédération',
);

// 11 — historique daté, en ajout seul.
$historique = $registre->resoudreHistorique($refPilote);
$etats = array_column($historique, 'etat');
$verifier(
    $etats === ['PREPARATION', 'ACTIF', 'SUSPENDU', 'ACTIF', 'RETIRE']
        && $historique[0]['date_effet'] <= $historique[count($historique) - 1]['date_effet'],
    'l’historique conserve chaque transition, datée, sans réécrire le passé',
);

// Second produit, pour les épreuves d'environnement et d'audience.
$registre->inscrireProduit($dossier([
    'reference' => $refSecond, 'identite_reference' => $IDN_SECOND,
    'nom_canonique' => 'Second P3', 'nom_affichage' => 'Second P3',
    'type_produit' => 'SATELLITE', 'proprietaire_reference' => $PROPRIETAIRE,
]));

// 12 — endpoint de production refusé sans HTTPS.
$httpRefuse = $registre->declarerEnvironnement($refSecond, $dossier([
    'environnement' => 'PRODUCTION',
    'api_base_url' => 'http://second.example/api',
    'audience_federation' => $refSecond,
]));
$httpsAccepte = $registre->declarerEnvironnement($refSecond, $dossier([
    'environnement' => 'PRODUCTION',
    'api_base_url' => 'https://second.example/api',
    'audience_federation' => $refSecond,
]));
$verifier(
    ($httpRefuse['refus'] ?? null) === 'URL_INVALIDE'
        && ($httpsAccepte['audience_federation'] ?? null) === $refSecond
        && ($httpsAccepte['actif'] ?? null) === true,
    'un environnement de production sans HTTPS est refusé',
);

// 13 — audience unique parmi les produits actifs.
$audiencePrise = $registre->declarerEnvironnement($refPilote, $dossier([
    'environnement' => 'PRODUCTION',
    'api_base_url' => 'https://pilote.example/api',
    'audience_federation' => $refSecond,
]));
$verifier(
    ($audiencePrise['refus'] ?? null) === 'AUDIENCE_DEJA_UTILISEE',
    'une audience de fédération n’appartient jamais à deux produits actifs',
);

// Une URL modifiée clôt l'ancienne version au lieu de la réécrire.
$nouvelleVersion = $registre->declarerEnvironnement($refSecond, $dossier([
    'environnement' => 'PRODUCTION',
    'api_base_url' => 'https://second.example/api/v2',
    'audience_federation' => $refSecond,
]));
$environnements = $registre->resoudreEnvironnements($refSecond);
$actifs = array_values(array_filter($environnements, static fn (array $e): bool => $e['actif'] === true));
$verifier(
    count($environnements) === 2
        && count($actifs) === 1
        && $actifs[0]['api_base_url'] === 'https://second.example/api/v2',
    'une URL modifiée clôt l’ancienne version sans la supprimer',
);

// 14 — aucun secret dans le magasin des produits.
$contenu = (string) file_get_contents($fichiers['produits']);
$interdits = ['password', 'secret', 'mot_de_passe', 'private_key', 'clé_privée'];
$trouves = [];
foreach ($interdits as $motif) {
    if (stripos($contenu, $motif) !== false) {
        $trouves[] = $motif;
    }
}
$verifier($trouves === [], 'le magasin des produits ne porte aucun secret');

// 17 — reconstruire l'index ne supprime jamais le registre des produits, même
// lorsque les deux magasins partagent la même connexion.
$partage = new PDO('sqlite::memory:');
$partage->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
BaselineOperationnelle::standard()->reconstruire($partage);
$registrePartage = new RegistreProduits($partage, $partage, $partage, new Ctr01($partage, $partage));
$registrePartage->inscrireProduit($dossier([
    'reference' => 'PRD-P3-PARTAGE', 'identite_reference' => 'PRD-GAMAD-002',
    'nom_canonique' => 'Partagé', 'nom_affichage' => 'Partagé',
    'type_produit' => 'SATELLITE', 'proprietaire_reference' => $PROPRIETAIRE,
]));
BaselineOperationnelle::standard()->reconstruire($partage);
$verifier(
    $registrePartage->resoudreProduit('PRD-P3-PARTAGE') !== null,
    'reconstruire l’index ne supprime jamais le registre persistant des produits',
);

// 18 — refus par défaut déjà couvert par l'épreuve 2 (CAP-CORE-004).
$verifier(($inconnue['decision'] ?? null) === 'REFUSÉ', 'refus par défaut : l’absence de règle n’est jamais une permission');

// Modification : champs immuables protégés, métadonnées modifiables.
$immuable = $registre->modifierProduit($refSecond, $dossier(['reference' => 'AUTRE']));
$modification = $registre->modifierProduit($refSecond, $dossier(['nom_affichage' => 'Second, renommé']));
$verifier(
    ($immuable['refus'] ?? null) === 'CHAMP_IMMUABLE'
        && ($modification['nom_affichage'] ?? null) === 'Second, renommé',
    'la référence et l’identité canonique ne se modifient jamais ; les autres métadonnées, oui',
);

echo "\n";
if ($echecs === 0) {
    echo "Garde CAP-CORE-011 : ÉTABLIE.\n";
    exit(0);
}
echo "Garde CAP-CORE-011 : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
