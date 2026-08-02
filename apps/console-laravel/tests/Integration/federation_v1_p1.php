<?php

declare(strict_types=1);

/**
 * Parcours fédéré de bout en bout sur le pilote GamaDrive, en HTTP réel.
 *
 * Suit les sept étapes du §7 de `docs/02-compte-gamad-et-federation.md` :
 * résolution du Compte GAMAD, authentification, ouverture depuis le Core,
 * provisionnement du compte local, accès du satellite, révocation, audit.
 *
 * Exécution depuis la racine du dépôt :
 *   php apps/console-laravel/tests/Integration/federation_v1_p1.php
 */

use Gamad\JournalOperationnel\Journal;
use Gamad\JournalOperationnel\Magasin as JournalMagasin;
use Gamad\RegistreAcces\Ctr16;
use Gamad\RegistreAcces\Magasin as AccesMagasin;
use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreIdentites\PolitiqueInscription;
use Gamad\RegistreNormes\BaselineOperationnelle;
use Gamad\RegistreNormes\Db;
use Gamad\RegistreProduits\Magasin as ProduitsMagasin;
use Gamad\RegistreProduits\PolitiqueProduits;
use Gamad\RegistreProduits\RegistreProduits;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

$application = dirname(__DIR__, 2);
$temp = sys_get_temp_dir() . '/gamad-federation-v1-' . getmypid();
$fichiers = [
    'index' => $temp . '-index.sqlite',
    'acces' => $temp . '-acces.sqlite',
    'identites' => $temp . '-identites.sqlite',
    'journal' => $temp . '-journal.sqlite',
    'produits' => $temp . '-produits.sqlite',
    'sources' => $temp . '-sources.sqlite',
    'politiques' => $temp . '-politiques.sqlite',
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
    'APP_KEY' => 'base64:' . base64_encode(str_repeat('f', 32)),
    'APP_CONFIG_CACHE' => $temp . '-config.php',
    'APP_EVENTS_CACHE' => $temp . '-events.php',
    'APP_PACKAGES_CACHE' => $temp . '-packages.php',
    'APP_ROUTES_CACHE' => $temp . '-routes.php',
    'APP_SERVICES_CACHE' => $temp . '-services.php',
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
    'PRODUCT_REGISTRY_URL' => '',
    'PRODUCT_REGISTRY_PATH' => $fichiers['produits'],
    'SOURCE_REGISTRY_URL' => '',
    'SOURCE_REGISTRY_PATH' => $fichiers['sources'],
    'POLICY_REGISTRY_URL' => '',
    'POLICY_REGISTRY_PATH' => $fichiers['politiques'],
];
foreach ($environnement as $cle => $valeur) {
    putenv("{$cle}={$valeur}");
    $_ENV[$cle] = $valeur;
    $_SERVER[$cle] = $valeur;
}

require $application . '/vendor/autoload.php';

$DRIVE = 'PRD-GAMAD-002';
$WASPLEX = 'PRD-GAMAD-003';

$index = Db::connect();
BaselineOperationnelle::standard()->reconstruire($index);
$registreIdentites = IdentiteMagasin::connecter();
$ctr16 = new Ctr16(AccesMagasin::connecter());

// CAP-CORE-011 en écriture gouvernée : GamaDrive est inscrit puis activé avec
// sa fédération explicitement autorisée, reproduisant l'état RECONNU déjà
// porté par la baseline documentaire. Wasplex reste en PREPARATION.
$ctr01 = new Ctr01($index, $registreIdentites);
$registreProduits = new RegistreProduits($index, $registreIdentites, ProduitsMagasin::connecter(), $ctr01);
$dossierProduit = static fn (array $extra = []): array => $extra + [
    'politique' => PolitiqueProduits::POLITIQUE,
    'source' => PolitiqueProduits::SOURCE,
    'producteur' => PolitiqueInscription::AUTORITE_INSCRIPTION,
    'preuve' => 'EVT-V1-P1-PRD-' . strtoupper(bin2hex(random_bytes(4))),
];
foreach (['PRD-GAMAD-002' => 'GamaDrive', 'PRD-GAMAD-003' => 'Wasplex'] as $ref => $libelle) {
    $registreProduits->inscrireProduit($dossierProduit([
        'reference' => $ref, 'identite_reference' => $ref,
        'nom_canonique' => $libelle, 'nom_affichage' => $libelle,
        'type_produit' => $ref === 'PRD-GAMAD-002' ? 'SATELLITE' : 'PARTENAIRE',
        'proprietaire_reference' => PolitiqueInscription::AUTORITE_INSCRIPTION,
    ]));
}
$registreProduits->modifierProduit('PRD-GAMAD-002', $dossierProduit(['federation_autorisee' => true]));
$registreProduits->activerProduit('PRD-GAMAD-002', $dossierProduit());
$secrets = [
    'AUT-GAMAD-001' => 'Secret-Federation-Autorite-1!',
    $DRIVE => 'Secret-Federation-GamaDrive-1!',
    $WASPLEX => 'Secret-Federation-Wasplex-1!',
];
foreach ($secrets as $entite => $secret) {
    $ctr16->inscrireAuthentificateur($entite, $secret);
}

$app = require $application . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->call('core:politiques:bootstrap');
$kernel = $app->make(Kernel::class);

$echecs = 0;
$verifier = static function (bool $ok, string $libelle) use (&$echecs): void {
    printf("  %s  %s\n", $ok ? '[OK]  ' : '[ÉCHEC]', $libelle);
    if (!$ok) {
        $echecs++;
    }
};
$requete = static function (
    string $methode,
    string $uri,
    ?array $json = null,
    ?string $jeton = null,
) use ($kernel): array {
    $serveur = ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'];
    if ($jeton !== null) {
        $serveur['HTTP_AUTHORIZATION'] = 'Bearer ' . $jeton;
    }
    $request = Request::create(
        $uri,
        $methode,
        [],
        [],
        [],
        $serveur,
        $json === null ? null : json_encode($json, JSON_THROW_ON_ERROR),
    );
    $response = $kernel->handle($request);
    $corps = json_decode((string) $response->getContent(), true);
    $resultat = [
        'statut' => $response->getStatusCode(),
        'corps' => is_array($corps) ? $corps : [],
    ];
    $kernel->terminate($request, $response);

    return $resultat;
};
$connecter = static function (string $entite) use ($requete, $secrets): string {
    $connexion = $requete('POST', '/api/v1/sessions', [
        'entite' => $entite,
        'secret' => $secrets[$entite],
    ]);

    return (string) ($connexion['corps']['jeton'] ?? '');
};

echo "INTÉGRATION HTTP — FÉDÉRATION V1 P1 (CAP-CORE-022)\n\n";

// 1 et 2 — Compte GAMAD résolu puis authentifié.
$sessionAutorite = $connecter('AUT-GAMAD-001');
$sessionDrive = $connecter($DRIVE);
$sessionWasplex = $connecter($WASPLEX);
$verifier(
    $sessionAutorite !== '' && $sessionDrive !== '' && $sessionWasplex !== '',
    'l’autorité et les deux satellites ouvrent chacun une session Core',
);

$sansSession = $requete('POST', "/api/v1/produits/{$DRIVE}/ouverture");
$verifier(
    $sansSession['statut'] === 401
        && ($sansSession['corps']['erreur'] ?? null) === 'AUTHENTIFICATION_REQUISE',
    'aucune ouverture fédérée n’est possible sans session',
);

$inscription = $requete('POST', '/api/v1/identites', [
    'canal' => 'AUTORITE',
    'type' => 'personne',
    'libelle' => 'Porteur du parcours fédéré',
], $sessionAutorite);
$porteur = (string) ($inscription['corps']['identite']['reference'] ?? '');
$verifier(
    $inscription['statut'] === 201 && str_starts_with($porteur, 'IDN-PER-'),
    'le Compte GAMAD du porteur est résolu par CAP-CORE-001',
);

// 3 — le Portail voit les satellites et l'état d'activation, rien de plus.
$catalogue = $requete('GET', '/api/v1/produits', null, $sessionAutorite);
$produits = array_column($catalogue['corps']['produits'] ?? [], null, 'reference');
$verifier(
    $catalogue['statut'] === 200
        && count($produits) === 2
        && ($produits[$DRIVE]['federable'] ?? null) === true
        && ($produits[$WASPLEX]['federable'] ?? null) === false,
    'le catalogue des satellites distingue le produit reconnu du partenaire externe',
);

$refusWasplex = $requete('POST', "/api/v1/produits/{$WASPLEX}/ouverture", [
    'identite' => $porteur,
], $sessionAutorite);
$verifier(
    $refusWasplex['statut'] === 422
        && ($refusWasplex['corps']['resultat']['refus'] ?? null) === 'PRODUIT_NON_RECONNU',
    'un partenaire externe non entériné n’est pas ouvrable',
);

// 4 — provisionnement du compte local, idempotent.
$ouverture = $requete('POST', "/api/v1/produits/{$DRIVE}/ouverture", [
    'identite' => $porteur,
    'relation_type' => 'CLIENT',
    'sujet_local_opaque' => 'drive-sujet-http-01',
], $sessionAutorite);
$jetonFedere = (string) ($ouverture['corps']['acces']['jeton'] ?? '');
$relation = (string) ($ouverture['corps']['acces']['relation'] ?? '');
$repetition = $requete('POST', "/api/v1/produits/{$DRIVE}/ouverture", [
    'identite' => $porteur,
], $sessionAutorite);
$liens = (int) (new PDO('sqlite:' . $fichiers['identites']))
    ->query("SELECT count(*) FROM relation_produit WHERE identite_reference = '{$porteur}'")
    ->fetchColumn();
$verifier(
    $ouverture['statut'] === 201
        && str_starts_with($jetonFedere, 'FED-')
        && ($ouverture['corps']['acces']['audience'] ?? null) === $DRIVE
        && ($repetition['corps']['acces']['relation'] ?? null) === $relation
        && $liens === 1,
    'l’ouverture provisionne un compte local unique et remet un jeton d’audience',
);
$corpsOuverture = json_encode($ouverture['corps'], JSON_UNESCAPED_UNICODE) ?: '';
$verifier(
    !str_contains($corpsOuverture, $sessionAutorite)
        && !str_contains($corpsOuverture, hash('sha256', $sessionAutorite))
        && !str_contains($corpsOuverture, 'session_empreinte'),
    'la réponse d’ouverture ne restitue ni la session Core ni son empreinte',
);

// 5 — le satellite échange le jeton contre sa session locale.
$verification = $requete('POST', "/api/v1/produits/{$DRIVE}/verification", [
    'jeton' => $jetonFedere,
], $sessionDrive);
$rejeu = $requete('POST', "/api/v1/produits/{$DRIVE}/verification", [
    'jeton' => $jetonFedere,
], $sessionDrive);
$verifier(
    $verification['statut'] === 200
        && ($verification['corps']['acces']['identite'] ?? null) === $porteur
        && ($verification['corps']['acces']['relation_type'] ?? null) === 'CLIENT'
        && $rejeu['statut'] === 401
        && ($rejeu['corps']['motif'] ?? null) === 'JETON_DEJA_CONSOMME',
    'le satellite consomme le jeton une fois et une seule',
);

// Borne centrale : un jeton GamaDrive n'ouvre pas Wasplex.
$jetonSuivant = (string) ($repetition['corps']['acces']['jeton'] ?? '');
$audienceEtrangere = $requete('POST', "/api/v1/produits/{$WASPLEX}/verification", [
    'jeton' => $jetonSuivant,
], $sessionWasplex);
$appelantEtranger = $requete('POST', "/api/v1/produits/{$DRIVE}/verification", [
    'jeton' => $jetonSuivant,
], $sessionWasplex);
$toujoursValide = $requete('POST', "/api/v1/produits/{$DRIVE}/verification", [
    'jeton' => $jetonSuivant,
], $sessionDrive);
$verifier(
    $audienceEtrangere['statut'] === 401
        && ($audienceEtrangere['corps']['motif'] ?? null) === 'AUDIENCE_ETRANGERE'
        && $appelantEtranger['statut'] === 403
        && ($appelantEtranger['corps']['erreur'] ?? null) === 'APPELANT_INCOMPETENT'
        && $toujoursValide['statut'] === 200,
    'un jeton destiné à GamaDrive reste inutilisable par Wasplex, sans préjudice',
);

// 6 — révocation par le satellite concerné.
$avantRevocation = $requete('POST', "/api/v1/produits/{$DRIVE}/ouverture", [
    'identite' => $porteur,
], $sessionAutorite);
$revocation = $requete('POST', "/api/v1/produits/{$DRIVE}/revocation", [
    'identite' => $porteur,
], $sessionDrive);
$apresRevocation = $requete('POST', "/api/v1/produits/{$DRIVE}/verification", [
    'jeton' => (string) ($avantRevocation['corps']['acces']['jeton'] ?? ''),
], $sessionDrive);
$identiteApres = $requete('GET', "/api/v1/identites/{$porteur}", null, $sessionAutorite);
$verifier(
    $revocation['statut'] === 200
        && ($revocation['corps']['revocation']['relation_etat'] ?? null) === 'CLOSE'
        && $apresRevocation['statut'] === 401
        && ($apresRevocation['corps']['motif'] ?? null) === 'JETON_REVOQUE'
        && ($identiteApres['corps']['etat'] ?? null) === 'ACTIVE',
    'la révocation ferme l’accès et les jetons sans supprimer l’identité GAMAD',
);

// Déconnexion globale : les jetons encore ouverts tombent avec la session.
$reprise = $requete('POST', "/api/v1/produits/{$DRIVE}/ouverture", [
    'identite' => $porteur,
], $sessionAutorite);
$deconnexion = $requete('DELETE', '/api/v1/sessions/current', null, $sessionAutorite);
$apresDeconnexion = $requete('POST', "/api/v1/produits/{$DRIVE}/verification", [
    'jeton' => (string) ($reprise['corps']['acces']['jeton'] ?? ''),
], $sessionDrive);
$verifier(
    $deconnexion['statut'] === 200
        && ($deconnexion['corps']['jetons_federes_fermes'] ?? 0) >= 1
        && $apresDeconnexion['statut'] === 401
        && in_array(
            $apresDeconnexion['corps']['motif'] ?? null,
            ['JETON_REVOQUE', 'SESSION_CORE_FERMEE'],
            true,
        ),
    'la déconnexion globale ferme les jetons fédérés encore ouverts',
);

// 8 — audit du parcours complet.
$integrite = (new Journal(JournalMagasin::ouvrir()))->verifierIntegrite();
$journalPdo = new PDO('sqlite:' . $fichiers['journal']);
$federation = (int) $journalPdo
    ->query("SELECT count(*) FROM evenement_operationnel WHERE categorie = 'FEDERATION'")
    ->fetchColumn();
$fuite = (int) $journalPdo
    ->query("SELECT count(*) FROM evenement_operationnel WHERE donnees LIKE '%FED-%'")
    ->fetchColumn();
$verifier(
    $integrite['valide'] === true && $federation >= 8 && $fuite === 0,
    'le parcours fédéré est chaîné dans l’audit, sans jamais y porter un jeton',
);

echo "\n";
if ($echecs === 0) {
    echo "Fédération v1 P1 : ÉTABLIE.\n";
    exit(0);
}

echo "Fédération v1 P1 : NON ÉTABLIE ({$echecs} écart(s)).\n";
exit(1);
