<?php

use Gamad\RegistreNormes\Db;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;

// Inclus par produits_v1_p1.php : même noyau HTTP, session et magasins isolés.
// Nouveau scénario : remettre à zéro les compteurs du cache array de test.
app('cache')->store()->flush();
$declaration = [
    'identifiant' => 'gamad.reseau', 'domaine_public' => 'reseau.gamad-afrique.com',
    'route_accueil' => 'reseau.accueil', 'route_console' => 'reseau.administration',
    'capacites' => ['administration', 'moderation'], 'revision' => 0,
];
$creerApplication = static function (string $reference) use ($requete, $sessionAutorite): void {
    $identite = $requete('POST', '/api/v1/identites', ['canal' => 'AUTORITE', 'type' => 'produit', 'libelle' => $reference], $sessionAutorite);
    $produit = $requete('POST', '/api/v1/produits', [
        'reference' => $reference, 'identite_reference' => $identite['corps']['identite']['reference'],
        'nom_canonique' => $reference, 'nom_affichage' => $reference,
        'type_produit' => 'APPLICATION_INTERNE', 'proprietaire_reference' => 'AUT-GAMAD-001',
    ], $sessionAutorite);
    if ($produit['statut'] !== 201) {
        throw new RuntimeException('Fixture applicative non créée');
    }
};
$creerApplication('PRD-APP-TEST-A');
$creerApplication('PRD-APP-TEST-B');
$uriApplication = '/api/v1/produits/PRD-APP-TEST-A/application';
$verifier($requete('PUT', $uriApplication, $declaration)['statut'] === 401, 'une déclaration applicative exige une vraie session Core');
$ctr16->inscrireAuthentificateur('AGENT-IA-001', 'Secret-Agent-Application!');
$jetonAgent = $ctr16->etablirSession('AGENT-IA-001', 'Secret-Agent-Application!')['session'];
$verifier($requete('PUT', $uriApplication, $declaration, $jetonAgent)['statut'] === 403, 'CAP-CORE-004 refuse la déclaration à un sujet non habilité');
$verifier($requete('PUT', "/api/v1/produits/{$REF}/application", $declaration, $sessionAutorite)['statut'] === 422, 'un satellite ne reçoit pas de déclaration interne');
$verifier($requete('PUT', $uriApplication, array_replace($declaration, ['route_console' => 'console.acces.index']), $sessionAutorite)['statut'] === 422, 'les routes arbitraires ne peuvent pas être injectées par le client');
$verifier($requete('PUT', $uriApplication, array_replace($declaration, ['capacites' => ['superadmin']]), $sessionAutorite)['statut'] === 422, 'une capacité absente du code livré est refusée');
$declaree = $requete('PUT', $uriApplication, $declaration + ['producteur' => 'USURPATEUR', 'preuve' => 'FAUX'], $sessionAutorite);
$pdoProduits = \Gamad\RegistreProduits\Magasin::connecter();
$registreApplications = new \Gamad\RegistreProduits\RegistreProduits(Db::connect(), IdentiteMagasin::connecter(), $pdoProduits);
$trace = $pdoProduits->query("SELECT producteur, preuve FROM produit_application_revision WHERE produit_reference = 'PRD-APP-TEST-A'")->fetch();
$verifier($declaree['statut'] === 200 && ($declaree['corps']['resultat']['revision'] ?? null) === 1
    && $trace['producteur'] === 'AUT-GAMAD-001' && $trace['preuve'] !== 'FAUX', 'la déclaration persistée conserve la preuve et le producteur Core, jamais ceux du client');
$verifier($registreApplications->resoudreApplicationParDomaine($declaration['domaine_public']) === null, 'PREPARATION ne résout aucun accueil public');
$requete('POST', '/api/v1/produits/PRD-APP-TEST-A/activation', [], $sessionAutorite);
$verifier(($registreApplications->resoudreApplicationParDomaine('RESEAU.GAMAD-AFRIQUE.COM.')['produit_reference'] ?? '') === 'PRD-APP-TEST-A'
    && $registreApplications->resoudreApplicationParDomaine('autre.gamad-afrique.com') === null,
    'la résolution utilise le domaine exact normalisé d’un produit actif');
$verifier($requete('PUT', $uriApplication, $declaration, $sessionAutorite)['statut'] === 409, 'une ancienne révision ne peut pas écraser la déclaration');
$verifier($requete('PUT', '/api/v1/produits/PRD-APP-TEST-B/application', $declaration, $sessionAutorite)['statut'] === 409
    && $pdoProduits->query("SELECT COUNT(*) FROM produit_application_revision WHERE produit_reference = 'PRD-APP-TEST-B'")->fetchColumn() == 0,
    'la collision persistée est refusée sans version partielle');
$verifier($requete('PATCH', '/api/v1/produits/PRD-APP-TEST-A', ['type_produit' => 'SATELLITE'], $sessionAutorite)['statut'] === 422
    && $requete('PATCH', '/api/v1/produits/PRD-APP-TEST-A', ['federation_autorisee' => true], $sessionAutorite)['statut'] === 422
    && !$registreApplications->verifierUtilisablePourFederation('PRD-APP-TEST-A')['utilisable'],
    'une application intégrée ne peut pas devenir une autorité fédérée');
$navigation = $requete('GET', $uriApplication.'/navigation', null, $sessionAutorite);
$verifier($navigation['statut'] === 200 && $navigation['corps']['liens'] === [], 'être propriétaire du produit ne donne aucun droit applicatif implicite');
// Fixture de politique uniquement : jamais installée par le code de production.
$politiques = \Gamad\RegistrePolitiques\Magasin::connecter();
$version = $politiques->query("SELECT id FROM politique_version WHERE politique_reference = 'POL-PRODUITS-V1' ORDER BY id DESC LIMIT 1")->fetchColumn();
$politiques->prepare('INSERT INTO regle_politique (politique_version_id, ordre, effet, action_reference, motif, sujet_reference, cree_le) VALUES (?, 990, ?, ?, ?, ?, ?)')
    ->execute([$version, 'PERMET', \App\Application\Produits\ApplicationsInstallees::action('gamad.reseau', 'moderation'), 'fixture de test', 'AUT-GAMAD-001', gmdate('c')]);
$navigation = $requete('GET', $uriApplication.'/navigation', null, $sessionAutorite);
$verifier(($navigation['corps']['liens'] ?? []) === ['moderation' => 'reseau.moderation'], 'seule la capacité explicitement autorisée apparaît dans la navigation');
$verifier($requete('GET', $uriApplication.'/navigation', null, $jetonAgent)['corps']['liens'] === [], 'les droits applicatifs restent propres au sujet Core');
$suspApp = $requete('POST', '/api/v1/produits/PRD-APP-TEST-A/suspension', [], $sessionAutorite);

$verifier($registreApplications->resoudreApplicationParDomaine($declaration['domaine_public']) === null
    && $requete('GET', $uriApplication.'/navigation', null, $sessionAutorite)['statut'] === 404, 'la suspension ferme la résolution de domaine et la navigation');
$verifier($requete('PUT', '/api/v1/produits/PRD-APP-TEST-B/application', $declaration, $sessionAutorite)['statut'] === 409, 'suspendre une application ne libère pas son domaine');
$requete('PUT', $uriApplication, array_replace($declaration, ['revision' => 1, 'domaine_public' => null]), $sessionAutorite);
$verifier($pdoProduits->query("SELECT COUNT(*) FROM produit_application_revision WHERE produit_reference = 'PRD-APP-TEST-A'")->fetchColumn() == 2
    && $registreApplications->resoudreApplication('PRD-APP-TEST-A')['domaine_public'] === null, 'le retrait explicite du domaine conserve les versions et l’identité applicative');
\Gamad\RegistreProduits\SchemaProduits::migrer($pdoProduits);
\Gamad\RegistreProduits\SchemaProduits::migrer($pdoProduits);
$verifier($registreApplications->resoudreApplication('PRD-APP-TEST-A')['revision'] === 2
    && $registreApplications->resoudreProduit($REF)['etat'] === 'RETIRE', 'la migration additive répétée préserve applications et produits historiques');
