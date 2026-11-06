<?php

declare(strict_types=1);

/**
 * Épreuve d'intégration du Compte GAMAD.
 *
 * Prouve qu'un SATELLITE ACTIF reconnu par CAP-CORE-011 peut spontanément
 * créer un Compte GAMAD pour autrui, sans règle individuelle ajoutée pour lui.
 * Prouve aussi qu'un partenaire ou un sujet ordinaire n'obtient pas ce droit,
 * et que les codes de possession ne franchissent pas la frontière HTTP Core -> satellite.
 */

use Gamad\RegistreAcces\Ctr16;
use Gamad\RegistreAcces\Magasin as AccesMagasin;
use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreIdentites\IdentifiantsResolution;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreNormes\BaselineOperationnelle;
use Gamad\RegistreNormes\Db;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

$application = dirname(__DIR__, 2);
$temp = sys_get_temp_dir() . '/gamad-compte-' . getmypid();
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
    'APP_KEY' => 'base64:' . base64_encode(str_repeat('g', 32)),
    'APP_CONFIG_CACHE' => $temp . '-config.php',
    'APP_EVENTS_CACHE' => $temp . '-events.php',
    'APP_PACKAGES_CACHE' => $temp . '-packages.php',
    'APP_ROUTES_CACHE' => $temp . '-routes.php',
    'APP_SERVICES_CACHE' => $temp . '-services.php',
    'CACHE_STORE' => 'array',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => ':memory:',
    'DB_URL' => '',
    'SESSION_DRIVER' => 'array',
    'LOG_CHANNEL' => 'errorlog',
    'MAIL_MAILER' => 'array',
    'MAIL_FROM_ADDRESS' => 'no-reply@example.test',
    'MAIL_FROM_NAME' => 'GAMAD',
    'GAMAD_VERIFICATION_EMAIL_ENABLED' => 'true',
    'GAMAD_VERIFICATION_EMAIL_MAILER' => 'array',
    'GAMAD_VERIFICATION_SMS_ENABLED' => 'true',
    'GAMAD_VERIFICATION_SMS_DRIVER' => 'array',
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
$resolution = new IdentifiantsResolution($registreIdentites);
$acces = AccesMagasin::connecter();

$app = require $application . '/bootstrap/app.php';
$console = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$console->call('core:politiques:bootstrap');
$console->call('core:produits:bootstrap');
$kernel = $app->make(Kernel::class);
set_exception_handler(static function (Throwable $exception): never {
    fwrite(STDERR, (string) $exception . PHP_EOL);
    exit(1);
});

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
    array $cookies = [],
    array $fichiersRequete = [],
) use ($kernel): array {
    $serveur = [
        'HTTP_ACCEPT' => 'application/json',
        'CONTENT_TYPE' => $fichiersRequete === [] ? 'application/json' : 'multipart/form-data',
    ];
    if ($jeton !== null) {
        $serveur['HTTP_AUTHORIZATION'] = 'Bearer ' . $jeton;
    }
    $request = Request::create(
        $uri,
        $methode,
        $fichiersRequete === [] ? [] : ($json ?? []),
        $cookies,
        $fichiersRequete,
        $serveur,
        $json === null || $fichiersRequete !== [] ? null : json_encode($json, JSON_THROW_ON_ERROR),
    );
    $app = app();
    if ($app->resolved('session')) {
        $app['session']->driver()->flush();
    }
    $response = $kernel->handle($request);
    $corps = json_decode((string) $response->getContent(), true);
    $resultat = [
        'statut' => $response->getStatusCode(),
        'location' => $response->headers->get('Location'),
        'html' => (string) $response->getContent(),
        'cookies' => array_column(array_map(
            static fn ($cookie): array => [$cookie->getName(), $cookie->getValue()],
            $response->headers->getCookies(),
        ), 1, 0),
        'corps' => is_array($corps) ? $corps : [],
    ];
    $kernel->terminate($request, $response);

    return $resultat;
};

$ctr16 = new Ctr16($acces);
$ouvrirProduit = static function (string $produit, string $secret) use ($ctr16): string {
    $ctr16->inscrireAuthentificateur($produit, $secret);
    $session = $ctr16->etablirSession($produit, $secret);
    return (string) ($session['session'] ?? '');
};

$satellite = 'PRD-GAMAD-002';
$partenaire = 'PRD-GAMAD-003';
$autorite = 'AUT-GAMAD-001';
$jetonSatellite = $ouvrirProduit($satellite, 'Secret-Satellite-Compte-2026!');
$jetonPartenaire = $ouvrirProduit($partenaire, 'Secret-Partenaire-Compte-2026!');
$jetonAutorite = $ouvrirProduit($autorite, 'Secret-Autorite-Compte-2026!');

echo "INTÉGRATION — COMPTE GAMAD P1\n\n";

$refusPartenaire = $requete('POST', '/api/v1/comptes', [
    'nom' => 'Personne partenaire refusée',
    'type_identifiant' => 'EMAIL',
    'identifiant' => 'partenaire.refus@example.test',
    'mot_de_passe' => 'Mot-de-passe-partenaire-2026!',
], $jetonPartenaire);
$verifier($refusPartenaire['statut'] === 403, 'un PARTENAIRE en préparation ne crée pas de compte');

$refusAutorite = $requete('POST', '/api/v1/comptes', [
    'nom' => 'Personne autorité refusée',
    'type_identifiant' => 'EMAIL',
    'identifiant' => 'autorite.refus@example.test',
    'mot_de_passe' => 'Mot-de-passe-autorite-2026!',
], $jetonAutorite);
$verifier($refusAutorite['statut'] === 403, 'un sujet ordinaire non habilité est refusé');

$email = 'Personne.Test+Satellite@Example.Test';
$motDePasse = 'Compte-GAMAD-email-2026!';
$creation = $requete('POST', '/api/v1/comptes', [
    'nom' => 'Personne Compte GAMAD',
    'type_identifiant' => 'EMAIL',
    'identifiant' => $email,
    'mot_de_passe' => $motDePasse,
], $jetonSatellite);
$reference = (string) ($creation['corps']['compte']['identite'] ?? '');
$rid = (string) ($creation['corps']['compte']['identifiant_reference'] ?? '');
$vrf = (string) ($creation['corps']['verification']['reference'] ?? '');
$verifier(
    $creation['statut'] === 201
        && str_starts_with($reference, 'IDN-PER-')
        && str_starts_with($rid, 'RID-')
        && str_starts_with($vrf, 'VRF-')
        && !array_key_exists('code', $creation['corps']['verification'] ?? [])
        && ($creation['corps']['verification']['livraison']['livree'] ?? false) === true
        && ($creation['corps']['verification']['livraison']['canal'] ?? null) === 'EMAIL'
        && !isset($creation['corps']['session']),
    'le satellite crée le compte, le Core livre le code et ne le révèle jamais dans la réponse',
);

$connexionAvant = $requete('POST', '/api/v1/sessions', [
    'identifiant' => 'personne.test+satellite@example.test',
    'type_identifiant' => 'EMAIL',
    'secret' => $motDePasse,
]);
$verifier($connexionAvant['statut'] === 401, 'un email NON_VERIFIE ne peut pas ouvrir de session');

$avantWeb = $requete('POST', '/connexion', [
    'entite' => $email, 'mode' => 'identifiant', 'secret' => $motDePasse,
]);
$verifier(
    $requete('GET', '/reseau/mon-compte', cookies: $avantWeb['cookies'])['statut'] === 302,
    'le navigateur refuse aussi la connexion par e-mail non vérifié',
);

// La garde interne ouvre un nouveau défi afin d'éprouver l'endpoint de validation
// sans réintroduire le code dans la réponse HTTP faite au satellite.
$defiInterne = $resolution->demarrerVerification($rid, [
    'source' => 'TEST-INTERNE', 'preuve' => 'TEST-VRF-EMAIL', 'producteur' => $satellite,
]);
$vrfInterne = (string) ($defiInterne['reference'] ?? '');
$codeInterne = (string) ($defiInterne['code'] ?? '');

$mauvaisCode = $requete('POST', '/api/v1/comptes/verifications', [
    'identite' => $reference,
    'identifiant_reference' => $rid,
    'verification_reference' => $vrfInterne,
    'code' => '000000' === $codeInterne ? '000001' : '000000',
], $jetonSatellite);
$verifier($mauvaisCode['statut'] === 422, 'un code de possession erroné est refusé');

$validation = $requete('POST', '/api/v1/comptes/verifications', [
    'identite' => $reference,
    'identifiant_reference' => $rid,
    'verification_reference' => $vrfInterne,
    'code' => $codeInterne,
], $jetonSatellite);
$verifier(
    $validation['statut'] === 200
        && ($validation['corps']['identifiant']['etat'] ?? null) === 'VERIFIE',
    'le code correct vérifie la possession',
);

$connexionEmail = $requete('POST', '/api/v1/sessions', [
    'identifiant' => 'personne.test+satellite@example.test',
    'type_identifiant' => 'EMAIL',
    'secret' => $motDePasse,
]);
$verifier(
    $connexionEmail['statut'] === 201 && ($connexionEmail['corps']['entite'] ?? null) === $reference,
    'après vérification la personne se reconnecte par email sans connaître son IDN',
);

// Un compte canonique ne dépend d’aucune table du stockage Laravel.
$verifier(
    !\Illuminate\Support\Facades\Schema::hasTable('users')
        && !\Illuminate\Support\Facades\Schema::hasTable('reseau_comptes_identites'),
    'le parcours se déroule sans table locale de comptes ou de liaison',
);

// Le navigateur utilise la même personne canonique et la vraie session CTR-16.
$verifier($requete('GET', '/reseau/mon-compte')['statut'] === 302, 'Réseau exige une session Core');
$connexionWeb = $requete('POST', '/connexion', [
    'entite' => 'personne.test+satellite@example.test',
    'mode' => 'identifiant',
    'secret' => $motDePasse,
]);
$compteReseau = $requete('GET', '/reseau/mon-compte', cookies: $connexionWeb['cookies']);
$verifier(
    $connexionWeb['statut'] === 302
        && $compteReseau['statut'] === 200
        && ($compteReseau['corps']['compte'] ?? null) === [
            'identite_reference' => $reference,
            'nom' => 'Personne Compte GAMAD',
            'bio' => null,
        ],
    'Réseau lit directement le compte et son nom Core avec la session du navigateur',
);
$cookies = $connexionWeb['cookies'];
$profil = $requete('GET', '/mon-profil', cookies: $cookies);
$verifier(
    $profil['statut'] === 200 && str_contains($profil['html'], 'Ma présentation'),
    'le formulaire du profil Core est accessible avec la même session',
);
$modifierProfil = $requete('PATCH', '/mon-profil', [
    'bio' => 'Je partage mon expérience en rénovation.',
    'identite_reference' => $autorite,
], cookies: $cookies);
$verifier(
    $modifierProfil['statut'] === 302
        && ($requete('GET', '/reseau/mon-compte', cookies: $cookies)['corps']['compte']['bio'] ?? null)
            === 'Je partage mon expérience en rénovation.'
        && $registreIdentites->query('SELECT COUNT(*) FROM profil_compte_gamad')->fetchColumn() === 1,
    'l’écriture Core alimente Réseau et ignore le sujet fourni par le client',
);
$profilLong = $requete('PATCH', '/mon-profil', ['bio' => str_repeat('é', 281)], cookies: $cookies);
$verifier(
    $profilLong['statut'] === 302
        && ($requete('GET', '/reseau/mon-compte', cookies: $cookies)['corps']['compte']['bio'] ?? null)
            === 'Je partage mon expérience en rénovation.',
    'la limite historique de 280 caractères est appliquée',
);
$requete('PATCH', '/mon-profil', ['bio' => ''], cookies: $cookies);
$profilEfface = $requete('GET', '/reseau/mon-compte', cookies: $cookies)['corps']['compte'];
$verifier(array_key_exists('bio', $profilEfface) && $profilEfface['bio'] === null, 'la présentation peut être effacée');
$requete('PATCH', '/mon-profil', [
    'bio' => 'Profil pour annuaire', 'visibilite' => 'members', 'apportees' => "Électricité\nElectricite",
    'recherchees' => 'Rénovation',
], cookies: $cookies);
$fichePublique = $registreIdentites->query('SELECT reference_publique FROM fiche_annuaire_gamad')->fetchColumn();
$uriFiche = '/personnes/'.$fichePublique;
$visiteur = $requete('GET', $uriFiche);
$membre = $requete('GET', '/personnes?q=electricite', cookies: $cookies);
$verifier(
    is_string($fichePublique) && $visiteur['statut'] === 404
        && $membre['statut'] === 200 && str_contains($membre['html'], $uriFiche),
    'la fiche membres est invisible au public et la recherche normalise les accents',
);
$requete('PATCH', '/mon-profil', [
    'bio' => 'Profil pour annuaire', 'visibilite' => 'public', 'apportees' => 'Électricité',
], cookies: $cookies);
$fiche = $requete('GET', $uriFiche);
$verifier(
    $fiche['statut'] === 200 && str_contains($fiche['html'], 'Profil pour annuaire')
        && !str_contains($fiche['html'], $reference) && !str_contains($fiche['html'], $email),
    'la publication révèle la présentation et les compétences sans identifiant canonique ni coordonnées',
);
$requete('PATCH', '/mon-profil', ['bio' => null, 'visibilite' => 'prive'], cookies: $cookies);
$verifier($requete('GET', $uriFiche)['statut'] === 404, 'rendre la fiche privée ferme aussi son URL directe');
$requete('PATCH', '/mon-profil', [
    'bio' => 'Profil pour annuaire', 'visibilite' => 'public', 'apportees' => 'Électricité',
], cookies: $cookies);
$verifier($requete('GET', '/fil')['statut'] === 302, 'le fil exige une session Core');
$privee = $requete('POST', '/fil', [
    'type' => 'knowledge', 'visibilite' => 'members', 'contenu' => 'Une expérience réservée aux membres.',
], cookies: $cookies);
$uriPrivee = parse_url($privee['location'] ?? '', PHP_URL_PATH) ?: '/publication-manquante';
$verifier(
    $privee['statut'] === 302 && $requete('GET', $uriPrivee)['statut'] === 404
        && $requete('GET', $uriPrivee, cookies: $cookies)['statut'] === 200,
    'une publication réservée reste inaccessible au visiteur, même par son URL directe',
);
$publique = $requete('POST', '/fil', [
    'type' => 'share', 'visibilite' => 'public', 'contenu' => 'Bonjour <script>GAMAD</script>.',
    'auteur_reference' => $autorite,
], cookies: $cookies);
$uriPublication = parse_url($publique['location'] ?? '', PHP_URL_PATH) ?: '/publication-manquante';
$fil = $requete('GET', '/fil', cookies: $cookies);
$fichePublication = $requete('GET', $uriPublication);
$verifier(
    $fil['statut'] === 200 && str_contains($fil['html'], $uriPublication)
        && $fichePublication['statut'] === 200
        && str_contains($fichePublication['html'], '&lt;script&gt;GAMAD&lt;/script&gt;')
        && str_contains($fichePublication['html'], 'Personne Compte GAMAD')
        && !str_contains($fichePublication['html'], $reference),
    'le fil et la fiche lisent le même contenu Core, échappent le HTML et utilisent l’auteur de la session',
);
$visiteurCommentaire = $requete('POST', $uriPublication.'/commentaires', ['contenu' => 'Visiteur']);
$membreCommentaire = $requete('POST', $uriPublication.'/commentaires', ['contenu' => 'Merci pour ce partage.'], cookies: $cookies);
$ficheCommentee = $requete('GET', $uriPublication);
$verifier(
    $visiteurCommentaire['statut'] === 302 && str_ends_with($visiteurCommentaire['location'] ?? '', '/connexion')
        && $membreCommentaire['statut'] === 302
        && str_contains($ficheCommentee['html'], 'Merci pour ce partage.'),
    'seule la session Core d’un membre vérifié permet de commenter',
);
$requete('GET', '/fil', cookies: $cookies);
$jetonWeb = $app['session']->driver()->get('gamad_session');
if (is_string($jetonWeb)) {
    $ctr16->revoquerSession($jetonWeb);
}
$verifier(
    is_string($jetonWeb) && $requete('GET', '/reseau/mon-compte', cookies: $connexionWeb['cookies'])['statut'] === 302,
    'la révocation CTR-16 ferme immédiatement l’accès Réseau',
);

$duplique = $requete('POST', '/api/v1/comptes', [
    'nom' => 'Tentative de doublon',
    'type_identifiant' => 'EMAIL',
    'identifiant' => 'PERSONNE.TEST+SATELLITE@example.test',
    'mot_de_passe' => 'Autre-secret-doublon-2026!',
], $jetonSatellite);
$verifier($duplique['statut'] === 409, 'le même email normalisé ne peut pas créer une seconde identité');

$telephone = '+225 07 18 71 37 81';
$motDePasseTel = 'Compte-GAMAD-phone-2026!';
$creationTel = $requete('POST', '/api/v1/comptes', [
    'nom' => 'Personne Téléphone',
    'type_identifiant' => 'TELEPHONE',
    'identifiant' => $telephone,
    'mot_de_passe' => $motDePasseTel,
], $jetonSatellite);
$referenceTel = (string) ($creationTel['corps']['compte']['identite'] ?? '');
$ridTel = (string) ($creationTel['corps']['compte']['identifiant_reference'] ?? '');
$verifier(
    $creationTel['statut'] === 201
        && str_starts_with($referenceTel, 'IDN-PER-')
        && !array_key_exists('code', $creationTel['corps']['verification'] ?? [])
        && ($creationTel['corps']['verification']['livraison']['livree'] ?? false) === true
        && ($creationTel['corps']['verification']['livraison']['canal'] ?? null) === 'TELEPHONE',
    'le téléphone suit la même frontière de livraison sans fuite du code',
);

$defiTel = $resolution->demarrerVerification($ridTel, [
    'source' => 'TEST-INTERNE', 'preuve' => 'TEST-VRF-TEL', 'producteur' => $satellite,
]);
$validationTel = $requete('POST', '/api/v1/comptes/verifications', [
    'identite' => $referenceTel,
    'identifiant_reference' => $ridTel,
    'verification_reference' => (string) ($defiTel['reference'] ?? ''),
    'code' => (string) ($defiTel['code'] ?? ''),
], $jetonSatellite);
$verifier($validationTel['statut'] === 200, 'la possession du téléphone est vérifiée');

$connexionTel = $requete('POST', '/api/v1/sessions', [
    'identifiant' => '+2250718713781',
    'type_identifiant' => 'TELEPHONE',
    'secret' => $motDePasseTel,
]);
$verifier(
    $connexionTel['statut'] === 201 && ($connexionTel['corps']['entite'] ?? null) === $referenceTel,
    'la personne se reconnecte par téléphone normalisé après vérification',
);

$directe = $requete('POST', '/api/v1/identites', [
    'canal' => 'PRODUIT_RECONNU',
    'type' => 'personne',
    'libelle' => 'Tentative inscription générique',
], $jetonSatellite);
$verifier(
    $directe['statut'] === 403,
    'le plein droit de créer des comptes ne donne pas au satellite un droit générique sur Identity Registry',
);

$identiteResolue = $ctr01->resoudreIdentite($reference);
$verifier(
    is_array($identiteResolue)
        && ($identiteResolue['type'] ?? null) === 'personne'
        && ($identiteResolue['etat'] ?? null) === 'ACTIVE',
    'le compte correspond à une vraie personne canonique active dans CAP-CORE-001',
);

echo "\n";
$connexionWeb = $requete('POST', '/connexion', ['entite' => $reference, 'secret' => $motDePasse]);
$resolution->attacher($referenceTel, 'EMAIL', 'membre-blocage@example.test', [
    'verifie' => true, 'source' => 'TEST', 'preuve' => 'TEST-EMAIL-BLOCAGE', 'producteur' => $satellite,
]);
$secondWeb = $requete('POST', '/connexion', ['entite' => $referenceTel, 'secret' => $motDePasseTel]);
$secondCookies = $secondWeb['cookies'];
$premierCookies = $connexionWeb['cookies'];
$requete('PATCH', '/mon-profil', [
    'bio' => 'Deuxième profil', 'visibilite' => 'public', 'apportees' => 'Rénovation',
], cookies: $secondCookies);
$referenceSecond = $registreIdentites->prepare('SELECT reference_publique FROM fiche_annuaire_gamad WHERE identite_reference = ?');
$referenceSecond->execute([$referenceTel]);
$uriSecond = '/personnes/'.$referenceSecond->fetchColumn();
$referenceSecond->closeCursor();
$uriBlocage = '/mon-profil/blocages/'.$fichePublique;
$relationVersPremier = '/relations/'.$fichePublique;
$relationVersSecond = '/relations/'.basename($uriSecond);
$demande = $requete('POST', $relationVersPremier, ['note' => 'Échangeons sur la rénovation.'], cookies: $secondCookies);
$reception = $requete('GET', '/relations', cookies: $premierCookies);
$duplicate = $requete('POST', $relationVersSecond, ['note' => 'Demande croisée'], cookies: $premierCookies);
$autoAcceptation = $requete('PATCH', $relationVersPremier, ['action' => 'accept', 'revision' => 1], cookies: $secondCookies);
$acceptation = $requete('PATCH', $relationVersSecond, ['action' => 'accept', 'revision' => 1], cookies: $premierCookies);
$ancienFormulaire = $requete('PATCH', $relationVersSecond, ['action' => 'decline', 'revision' => 1], cookies: $premierCookies);
$etatRelation = $requete('GET', '/relations', cookies: $secondCookies);
$verifier(
    $demande['statut'] === 302 && str_contains($reception['html'], 'Échangeons sur la rénovation.')
        && $duplicate['statut'] === 409 && $autoAcceptation['statut'] === 403
        && $acceptation['statut'] === 302 && $ancienFormulaire['statut'] === 409
        && str_contains($etatRelation['html'], 'En relation'),
    'une demande est unique par paire, seul le destinataire l’accepte et une décision périmée est refusée',
);
$conversation = '/conversations/'.$fichePublique;
$conversationInverse = '/conversations/'.basename($uriSecond);
$envoi = $requete('POST', $conversation, ['texte' => 'Message privé de test'], cookies: $secondCookies);
$lecture = $requete('GET', $conversationInverse, cookies: $premierCookies);
$notifications = $requete('GET', '/notifications', cookies: $premierCookies);
$notice = $registreIdentites->prepare("SELECT reference FROM notification_reseau_gamad WHERE destinataire_reference = ? AND type = 'message'");
$notice->execute([$reference]);
$noticeUri = '/notifications/'.$notice->fetchColumn().'/lire';
$notice->closeCursor();
$verifier(
    $envoi['statut'] === 302 && str_contains($lecture['html'], 'Message privé de test')
        && str_contains($notifications['html'], 'vous a envoyé un message')
        && $requete('POST', $noticeUri, cookies: $secondCookies)['statut'] === 404
        && $requete('POST', $noticeUri, cookies: $premierCookies)['statut'] === 302,
    'les deux membres lisent la conversation et seule la destination peut lire sa notification',
);
$contenuModeration = $requete('POST', '/fil', [
    'type' => 'share', 'visibilite' => 'public', 'contenu' => 'Contenu de test à modérer.',
], cookies: $premierCookies);
$uriModeration = parse_url($contenuModeration['location'] ?? '', PHP_URL_PATH) ?: '/publication-manquante';
$signalement = $requete('POST', $uriModeration.'/signalements', ['motif' => 'spam'], cookies: $secondCookies);
$signalements = $registreIdentites->query('SELECT reference FROM signalement_reseau_gamad');
$uriDecision = '/console/moderation-reseau/'.$signalements->fetchColumn();
$signalements->closeCursor();
$nonAdmin = $requete('POST', $uriDecision, ['action' => 'masquer'], cookies: $secondCookies);
$admission = $console->call('core:console:accorder-acces-administratif');
$secretModeration = 'Secret-moderation-GAMAD-2026';
$ctr16->inscrireAuthentificateur($autorite, $secretModeration);
$admin = $requete('POST', '/connexion', ['entite' => $autorite, 'secret' => $secretModeration]);
$ecranModeration = $requete('GET', '/console/moderation-reseau', cookies: $admin['cookies']);
$decision = $requete('POST', $uriDecision, ['action' => 'masquer'], cookies: $admin['cookies']);
$verifier(
    $signalement['statut'] === 302 && $nonAdmin['statut'] === 403
        && $admission === 0 && str_contains($ecranModeration['html'], 'Contenu de test à modérer.')
        && $decision['statut'] === 302 && $requete('GET', $uriModeration)['statut'] === 404,
    'seule la console Core traite le signalement et le masquage retire l’URL publique',
);
$avantBlocage = $requete('GET', $uriPublication, cookies: $secondCookies);
$blocage = $requete('POST', $uriBlocage, cookies: $secondCookies);
$listeBlocages = $requete('GET', '/mon-profil/blocages', cookies: $secondCookies);
$filBloque = $requete('GET', '/fil', cookies: $secondCookies);
$verifier(
    $avantBlocage['statut'] === 200 && $blocage['statut'] === 302
        && str_contains($listeBlocages['html'], 'Personne Compte GAMAD')
        && $filBloque['statut'] === 200 && !str_contains($filBloque['html'], $uriPublication)
        && $requete('GET', $uriPublication, cookies: $secondCookies)['statut'] === 404
        && $requete('GET', $uriFiche, cookies: $secondCookies)['statut'] === 404
        && $requete('GET', $uriSecond, cookies: $premierCookies)['statut'] === 404
        && $requete('POST', $uriPublication.'/commentaires', ['contenu' => 'Bloqué'], cookies: $secondCookies)['statut'] === 404,
    'le blocage exclut réciproquement les profils et ferme le fil, les URL directes et les commentaires concernés',
);
$verifier(
    $requete('GET', $conversation, cookies: $secondCookies)['statut'] === 404
        && $requete('POST', $conversationInverse, ['texte' => 'Bloqué'], cookies: $premierCookies)['statut'] === 404,
    'le blocage ferme la lecture et l’écriture de la conversation',
);
$requete('DELETE', $uriBlocage, cookies: $secondCookies);
$verifier(
    $requete('GET', $uriPublication, cookies: $secondCookies)['statut'] === 200
        && $requete('GET', $uriSecond, cookies: $premierCookies)['statut'] === 200,
    'le déblocage rétablit la visibilité permise',
);
$relationRetiree = $requete('GET', '/relations', cookies: $premierCookies);
$nouvelleDemande = $requete('POST', $relationVersPremier, ['note' => 'Nouvelle demande'], cookies: $secondCookies);
$verifier(
    !str_contains($relationRetiree['html'], 'En relation') && $nouvelleDemande['statut'] === 409,
    'le blocage retire la relation et le déblocage ne la rétablit pas',
);
$creationZumra = $requete('POST', '/zumra', [
    'nom' => 'ZUMRA de qualification', 'presentation' => 'Communauté de test ouverte.',
], cookies: $premierCookies);
$uriZumra = parse_url($creationZumra['location'] ?? '', PHP_URL_PATH) ?: '/zumra-manquante';
$creationProjet = $requete('POST', '/projets', [
    'zumra' => basename($uriZumra), 'titre' => 'Projet de qualification', 'dossier' => 'Dossier réservé au porteur.',
], cookies: $secondCookies);
$uriProjet = parse_url($creationProjet['location'] ?? '', PHP_URL_PATH) ?: '/projet-manquant';
$accueilProjet = $uriZumra.'/accueil/'.basename($uriProjet);
$demandeAccueil = $requete('GET', $uriZumra, cookies: $premierCookies);
$projetPrive = $requete('GET', $uriProjet, cookies: $secondCookies);
$verifier(
    $creationZumra['statut'] === 302 && $requete('GET', $uriZumra)['statut'] === 200
        && $creationProjet['statut'] === 302 && str_contains($projetPrive['html'], 'Dossier réservé au porteur.')
        && str_contains($demandeAccueil['html'], 'Projet de qualification')
        && !str_contains($demandeAccueil['html'], 'Dossier réservé au porteur.')
        && $requete('GET', $uriProjet, cookies: $premierCookies)['statut'] === 404
        && $requete('GET', $uriProjet)['statut'] === 404,
    'le fondateur voit la demande d’accueil mais le dossier reste privé, même par URL directe',
);
$publicationPrematuree = $requete('POST', $uriProjet.'/vitrine', [
    'action' => 'publier', 'vitrine' => 'Résumé public distinct.',
], cookies: $secondCookies);
$autoAccueil = $requete('POST', $accueilProjet, ['decision' => 'accepter'], cookies: $secondCookies);
$accueilAccepte = $requete('POST', $accueilProjet, ['decision' => 'accepter'], cookies: $premierCookies);
$publicationTiers = $requete('POST', $uriProjet.'/vitrine', [
    'action' => 'publier', 'vitrine' => 'Résumé public distinct.',
], cookies: $premierCookies);
$publication = $requete('POST', $uriProjet.'/vitrine', [
    'action' => 'publier', 'vitrine' => 'Résumé public distinct.',
], cookies: $secondCookies);
$vitrine = $requete('GET', $uriProjet);
$verifier(
    $publicationPrematuree['statut'] === 404 && $autoAccueil['statut'] === 404
        && $accueilAccepte['statut'] === 302 && $publicationTiers['statut'] === 404
        && $publication['statut'] === 302 && $vitrine['statut'] === 200
        && str_contains($vitrine['html'], 'Résumé public distinct.')
        && !str_contains($vitrine['html'], 'Dossier réservé au porteur.')
        && str_contains($requete('GET', '/projets')['html'], $uriProjet),
    'la vitrine devient publique seulement après accueil accepté et publication par le porteur',
);
$requete('POST', $uriProjet.'/vitrine', ['action' => 'retirer'], cookies: $secondCookies);
$verifier($requete('GET', $uriProjet)['statut'] === 404, 'le retrait ferme immédiatement la vitrine');
$adhesion = $requete('POST', $uriZumra.'/adhesion', ['action' => 'rejoindre'], cookies: $secondCookies);
$acceptationAdhesion = $requete('POST', $uriZumra.'/membres', [
    'action' => 'accepter', 'cible' => $referenceTel,
], cookies: $premierCookies);
$verifier(
    $adhesion['statut'] === 302 && $acceptationAdhesion['statut'] === 302
        && str_contains($requete('GET', $uriZumra, cookies: $secondCookies)['html'], 'Membre de la ZUMRA'),
    'la demande d’adhésion devient effective après décision du fondateur',
);
$delegation = $requete('POST', $uriZumra.'/responsabilites', [
    'cible' => $referenceTel, 'action' => 'nommer',
], cookies: $premierCookies);
$interdictionDeleguer = $requete('POST', $uriZumra.'/responsabilites', [
    'cible' => $referenceTel, 'action' => 'revoquer',
], cookies: $secondCookies);
$delegue = $requete('GET', $uriZumra, cookies: $secondCookies);
$retraitDelegation = $requete('POST', $uriZumra.'/responsabilites', [
    'cible' => $referenceTel, 'action' => 'revoquer',
], cookies: $premierCookies);
$verifier(
    $delegation['statut'] === 302 && $interdictionDeleguer['statut'] === 404
        && str_contains($delegue['html'], 'Demandes d’accueil')
        && $retraitDelegation['statut'] === 302
        && !str_contains($requete('GET', $uriZumra, cookies: $secondCookies)['html'], 'Demandes d’accueil'),
    'seul le fondateur délègue les droits de gestion et le retrait prend effet immédiatement',
);
$uriEtapes = $uriProjet.'/etapes';
$premiereEtape = $requete('POST', $uriEtapes, ['titre' => 'Préparation confidentielle'], cookies: $secondCookies);
$deuxiemeEtape = $requete('POST', $uriEtapes, ['titre' => 'Validation confidentielle'], cookies: $secondCookies);
$referenceEtape = $registreIdentites->prepare('SELECT reference FROM etape_projet_gamad WHERE projet_reference = ? AND position = 2');
$referenceEtape->execute([basename($uriProjet)]);
$uriEtape = $uriEtapes.'/'.$referenceEtape->fetchColumn();
$referenceEtape->closeCursor();
$deplacementEtape = $requete('POST', $uriEtape, ['action' => 'monter'], cookies: $secondCookies);
$renommageEtape = $requete('POST', $uriEtape, [
    'action' => 'enregistrer', 'titre' => 'Vérification confidentielle', 'etat' => 'done',
], cookies: $secondCookies);
$etapesPrivees = $requete('GET', $uriProjet, cookies: $secondCookies);
$verifier(
    $premiereEtape['statut'] === 302 && $deuxiemeEtape['statut'] === 302
        && $deplacementEtape['statut'] === 302 && $renommageEtape['statut'] === 302
        && str_contains($etapesPrivees['html'], 'Vérification confidentielle')
        && strpos($etapesPrivees['html'], 'Vérification confidentielle') < strpos($etapesPrivees['html'], 'Préparation confidentielle')
        && $requete('POST', $uriEtape, ['action' => 'retirer'], cookies: $premierCookies)['statut'] === 404
        && $requete('GET', $uriProjet)['statut'] === 404,
    'le porteur ordonne ses étapes, change leur état et garde leurs titres privés',
);
$invitationProjet = $requete('POST', $uriProjet.'/invitations', ['profil' => $fichePublique], cookies: $secondCookies);
$avantEquipe = $requete('GET', $uriProjet, cookies: $premierCookies);
$acceptationEquipe = $requete('POST', $uriProjet.'/equipe', ['action' => 'accepter'], cookies: $premierCookies);
$apresEquipe = $requete('GET', $uriProjet, cookies: $premierCookies);
$retraitEquipe = $requete('POST', $uriProjet.'/equipe', ['action' => 'retirer', 'cible' => $reference], cookies: $secondCookies);
$verifier(
    $invitationProjet['statut'] === 302 && str_contains($avantEquipe['html'], 'Vous êtes invité')
        && !str_contains($avantEquipe['html'], 'Dossier réservé au porteur.')
        && $acceptationEquipe['statut'] === 302 && str_contains($apresEquipe['html'], 'Dossier réservé au porteur.')
        && $retraitEquipe['statut'] === 302 && $requete('GET', $uriProjet, cookies: $premierCookies)['statut'] === 404,
    'seul un collaborateur qui a accepté son invitation accède au dossier et le retrait ferme cet accès',
);
$suppressionEtape = $requete('POST', $uriEtape, ['action' => 'retirer'], cookies: $secondCookies);
$verifier(
    $suppressionEtape['statut'] === 302
        && !str_contains($requete('GET', $uriProjet, cookies: $secondCookies)['html'], 'Vérification confidentielle'),
    'le porteur peut retirer une étape',
);
$uriMode = $uriZumra.'/mode-adhesion';
$modeNonAutorise = $requete('POST', $uriMode, ['mode' => 'open'], cookies: $secondCookies);
$modeOuvert = $requete('POST', $uriMode, ['mode' => 'open'], cookies: $premierCookies);
$requete('POST', $uriZumra.'/adhesion', ['action' => 'quitter'], cookies: $secondCookies);
$adhesionOuverte = $requete('POST', $uriZumra.'/adhesion', ['action' => 'rejoindre'], cookies: $secondCookies);
$verifier(
    $modeNonAutorise['statut'] === 404 && $modeOuvert['statut'] === 302 && $adhesionOuverte['statut'] === 302
        && str_contains($requete('GET', $uriZumra, cookies: $secondCookies)['html'], 'Membre de la ZUMRA'),
    'un responsable règle le mode et une adhésion ouverte devient effective immédiatement',
);
$uriParticipation = $uriProjet.'/participations';
$proposition = ['action' => 'proposer', 'message' => 'Je peux aider à préparer le chantier.'];
$projetSansVitrine = $requete('POST', $uriParticipation, $proposition, cookies: $premierCookies);
$requete('POST', $uriProjet.'/vitrine', [
    'action' => 'publier', 'vitrine' => 'Projet ouvert aux propositions.',
], cookies: $secondCookies);
$demandeParticipation = $requete('POST', $uriParticipation, $proposition, cookies: $premierCookies);
$porteurVoit = $requete('GET', $uriProjet, cookies: $secondCookies);
$publicNeVoitPas = $requete('GET', $uriProjet);
$retraitParticipation = $requete('POST', $uriParticipation, ['action' => 'retirer'], cookies: $premierCookies);
$nouvelleParticipation = $requete('POST', $uriParticipation, $proposition, cookies: $premierCookies);
$autoDecision = $requete('POST', $uriParticipation, ['action' => 'accepter', 'cible' => $reference], cookies: $premierCookies);
$decisionParticipation = $requete('POST', $uriParticipation, ['action' => 'accepter', 'cible' => $reference], cookies: $secondCookies);
$collaborateurVoit = $requete('GET', $uriProjet, cookies: $premierCookies);
$verifier(
    $projetSansVitrine['statut'] === 404 && $demandeParticipation['statut'] === 302
        && str_contains($porteurVoit['html'], $proposition['message'])
        && !str_contains($publicNeVoitPas['html'], $proposition['message'])
        && $retraitParticipation['statut'] === 302 && $nouvelleParticipation['statut'] === 302
        && $autoDecision['statut'] === 404 && $decisionParticipation['statut'] === 302
        && str_contains($collaborateurVoit['html'], 'Dossier réservé au porteur.'),
    'une proposition privée depuis la vitrine peut être retirée, resoumise et acceptée seulement par le porteur',
);
$cheminRccm = tempnam(sys_get_temp_dir(), 'gamad-rccm-');
file_put_contents($cheminRccm, "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n%%EOF");
try {
    $dossierEntreprise = $requete('POST', '/entreprises', [
        'nom' => 'Entreprise de qualification', 'pays' => 'CI', 'immatriculation' => 'CI TEST 001', 'secteur' => 'Rénovation',
    ], cookies: $premierCookies, fichiersRequete: [
        'rccm' => new \Illuminate\Http\UploadedFile($cheminRccm, 'rccm.pdf', 'application/pdf', null, true),
        'dfe' => new \Illuminate\Http\UploadedFile($cheminRccm, 'dfe.pdf', 'application/pdf', null, true),
    ]);
} finally {
    unlink($cheminRccm);
}
$uriEntreprise = parse_url($dossierEntreprise['location'] ?? '', PHP_URL_PATH) ?: '/entreprise-manquante';
$uriRevue = '/console/entreprises/'.basename($uriEntreprise);
$offre = ['titre' => 'Mission de qualification', 'description' => 'Une mission ouverte aux membres.'];
$interdite = $requete('POST', $uriEntreprise.'/opportunites', $offre, cookies: $premierCookies);
$verifier(
    $dossierEntreprise['statut'] === 302 && $requete('GET', $uriEntreprise)['statut'] === 404
        && $interdite['statut'] === 404
        && $requete('GET', $uriRevue.'/justificatif', cookies: $secondCookies)['statut'] === 403
        && str_starts_with($requete('GET', $uriRevue.'/justificatif', cookies: $admin['cookies'])['html'], '%PDF-'),
    'le justificatif est réservé à la console et une entreprise non vérifiée ne publie pas',
);
$dfePrivee = $requete('GET', $uriRevue.'/justificatif/dfe', cookies: $secondCookies);
$dfeConsole = $requete('GET', $uriRevue.'/justificatif/dfe', cookies: $admin['cookies']);
$decisionRefus = $requete('POST', $uriRevue, ['action' => 'declined', 'revision' => 0], cookies: $admin['cookies']);
$dossierRefuse = $requete('GET', $uriEntreprise, cookies: $premierCookies);
$cheminCorrection = tempnam(sys_get_temp_dir(), 'gamad-rccm-correction-');
$correction = "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n% Correction\n%%EOF";
file_put_contents($cheminCorrection, $correction);
try {
    $fichiersCorrection = [
        'rccm' => new \Illuminate\Http\UploadedFile($cheminCorrection, 'rccm.pdf', 'application/pdf', null, true),
        'idu' => new \Illuminate\Http\UploadedFile($cheminCorrection, 'idu.pdf', 'application/pdf', null, true),
    ];
    $renvoiTiers = $requete('POST', $uriEntreprise.'/resoumettre', cookies: $secondCookies, fichiersRequete: $fichiersCorrection);
    $renvoiProprietaire = $requete('POST', $uriEntreprise.'/resoumettre', cookies: $premierCookies, fichiersRequete: $fichiersCorrection);
} finally {
    unlink($cheminCorrection);
}
$verifier(
    $dfePrivee['statut'] === 403 && str_starts_with($dfeConsole['html'], '%PDF-')
        && $decisionRefus['statut'] === 302 && str_contains($dossierRefuse['html'], 'Soumettre à nouveau')
        && $renvoiTiers['statut'] === 404 && $renvoiProprietaire['statut'] === 302
        && $requete('GET', $uriRevue.'/justificatif', cookies: $admin['cookies'])['html'] === $correction
        && $requete('GET', $uriRevue.'/justificatif/idu', cookies: $admin['cookies'])['html'] === $correction,
    'les pièces complémentaires sont privées et seul le propriétaire peut corriger un dossier refusé',
);
$decisionPerimee = $requete('POST', $uriRevue, ['action' => 'verified', 'revision' => 0], cookies: $admin['cookies']);
$verifier($decisionPerimee['statut'] === 409, 'une décision sur un ancien dossier est refusée après correction');
$verificationEntreprise = $requete('POST', $uriRevue, ['action' => 'verified', 'revision' => 1], cookies: $admin['cookies']);
$publicationOffre = $requete('POST', $uriEntreprise.'/opportunites', $offre, cookies: $premierCookies);
$uriOffre = parse_url($publicationOffre['location'] ?? '', PHP_URL_PATH) ?: '/opportunite-manquante';
$candidature = ['message' => 'Candidature privée de qualification.'];
$envoiCandidature = $requete('POST', $uriOffre.'/candidatures', $candidature, cookies: $secondCookies);
$verifier(
    $verificationEntreprise['statut'] === 302 && $publicationOffre['statut'] === 302
        && $requete('GET', $uriOffre)['statut'] === 200 && $envoiCandidature['statut'] === 302
        && !str_contains($requete('GET', $uriOffre)['html'], $candidature['message'])
        && str_contains($requete('GET', $uriOffre, cookies: $premierCookies)['html'], $candidature['message']),
    'une entreprise vérifiée publie et la candidature reste privée',
);
$requete('POST', $uriOffre.'/cloture', cookies: $premierCookies);
$verifier(
    $requete('POST', $uriOffre.'/candidatures', $candidature, cookies: $secondCookies)['statut'] === 409
        && str_contains($requete('GET', $uriOffre)['html'], 'Clôturée'),
    'la clôture maintient la fiche visible et ferme les candidatures',
);
$requete('POST', $uriRevue, ['action' => 'suspended', 'revision' => 1], cookies: $admin['cookies']);
$verifier($requete('GET', $uriEntreprise)['statut'] === 404 && $requete('GET', $uriOffre)['statut'] === 404,
    'la suspension Core de l’entreprise retire ses opportunités publiques');
$messageContact = ['nom' => 'Contact de test', 'email' => 'contact-test@example.test', 'sujet' => 'account',
    'message' => 'Message privé destiné à la console Core.'];
$contactPublic = $requete('GET', '/contact');
$contactRobot = $requete('POST', '/contact', $messageContact + ['website' => 'robot']);
$contactEnvoye = $requete('POST', '/contact', $messageContact);
$contactStocke = $registreIdentites->query('SELECT reference FROM contact_reseau_gamad');
$referencesContact = $contactStocke->fetchAll(PDO::FETCH_COLUMN);
$uriContact = '/console/contact/'.($referencesContact[0] ?? 'manquant');
$contactInterdit = $requete('GET', $uriContact, cookies: $secondCookies);
$administrationReseau = $requete('GET', '/console/reseau', cookies: $admin['cookies']);
$verifier(
    $requete('GET', '/console/reseau')['statut'] === 302
        && $requete('GET', '/console/reseau', cookies: $secondCookies)['statut'] === 403
        && $administrationReseau['statut'] === 200
        && str_contains($administrationReseau['html'], '/console/moderation-reseau')
        && str_contains($administrationReseau['html'], '/console/entreprises')
        && str_contains($administrationReseau['html'], '/console/contact')
        && str_contains($administrationReseau['html'], '1 en attente')
        && !str_contains($administrationReseau['html'], $messageContact['message']),
    'le tableau Réseau est réservé à la console, relie les trois files et ne présente que les compteurs',
);
$contactConsole = $requete('GET', $uriContact, cookies: $admin['cookies']);
$verifier(
    $contactPublic['statut'] === 200 && $contactRobot['statut'] === 302 && $contactEnvoye['statut'] === 302
        && count($referencesContact) === 1 && $contactInterdit['statut'] === 403
        && $contactConsole['statut'] === 200 && str_contains($contactConsole['html'], $messageContact['message']),
    'le contact est public, le piège écarte le robot et seule la console lit le message',
);
$requete('POST', $uriContact, ['action' => 'traiter'], cookies: $admin['cookies']);
$statutContact = $registreIdentites->query('SELECT etat FROM contact_reseau_gamad')->fetchColumn();
$requete('POST', $uriContact, ['action' => 'supprimer'], cookies: $admin['cookies']);
$verifier(
    $statutContact === 'handled' && $requete('GET', $uriContact, cookies: $admin['cookies'])['statut'] === 404,
    'la console traite puis supprime le message',
);
$cycle = $registreIdentites->prepare(
    'INSERT INTO evenement_cycle_identite
     (identite_reference, evenement_type, etat_avant, etat_apres, source, preuve_reference,
      politique_inscription, acteur_reference, date_effet) VALUES (?,?,?,?,?,?,?,?,?)'
);
$evenement = [$reference, 'REVOCATION', 'ACTIVE', 'REVOQUEE', 'TEST', 'TEST', 'TEST', $autorite];
$cycle->execute([...$evenement, gmdate('Y-m-d', time() + 86400)]);
$verifier(
    $requete('GET', '/reseau/mon-compte', cookies: $connexionWeb['cookies'])['statut'] === 200,
    'une révocation future laisse le compte accessible jusqu’à sa date d’effet',
);
$cycle->execute([...$evenement, gmdate('Y-m-d')]);
$verifier(
    $requete('GET', '/reseau/mon-compte', cookies: $connexionWeb['cookies'])['statut'] === 403,
    'la révocation effective de l’identité ferme l’accès même avec une session CTR-16 encore valide',
);

$verifier($requete('GET', $uriFiche)['statut'] === 404, 'la révocation de la personne retire sa fiche publique');

$verifier($requete('GET', $uriPublication)['statut'] === 404, 'la révocation Core retire la publication publique');

$migrations = $application . '/database/migrations/';
(require $migrations . '0001_01_01_000000_create_users_table.php')->up();
(require $migrations . '2026_11_06_000001_create_reseau_comptes_identites_table.php')->up();
$base = \Illuminate\Support\Facades\DB::connection();
$base->table('users')->insert(['id' => 17, 'name' => 'Ancien', 'email' => 'archive@example.test', 'password' => 'archive']);
$ligne = ['id' => 1, 'user_id' => 17, 'identite_reference' => $reference];
$base->table('reseau_comptes_identites')->insert($ligne);
$archivage = require $migrations . '2026_11_06_000002_archiver_reseau_comptes_identites.php';
$archivage->up();
$verifier(
    !$base->getSchemaBuilder()->hasTable('reseau_comptes_identites')
        && $base->table('archive_reseau_comptes_identites')->where($ligne)->exists()
        && $base->table('users')->where('id', 17)->exists(),
    'la migration retire la table active en préservant les lignes et leur compte historique',
);
$archivage->down();
$verifier(
    !$base->getSchemaBuilder()->hasTable('archive_reseau_comptes_identites')
        && $base->table('reseau_comptes_identites')->where($ligne)->exists(),
    'le retour arrière restaure la table et ses lignes',
);

$archivage->up();
$retrait = require $migrations . '2026_11_06_000003_retirer_comptes_laravel_historiques.php';
try {
    $retrait->up();
    $verifier(false, 'le retrait doit refuser un stockage historique non vide');
} catch (RuntimeException) {
    $verifier($base->table('archive_reseau_comptes_identites')->where($ligne)->exists(), 'le retrait refuse un stockage historique non vide');
}
$base->table('archive_reseau_comptes_identites')->delete();
$base->table('users')->delete();
$retrait->up();
$schema = $base->getSchemaBuilder();
$verifier(
    !$schema->hasTable('users') && !$schema->hasTable('password_reset_tokens')
        && !$schema->hasTable('archive_reseau_comptes_identites') && $schema->hasTable('sessions'),
    'une installation neuve ne conserve que le transport HTTP, sans tables de comptes Laravel',
);

if ($echecs === 0) {
    echo "Compte GAMAD P1 : ÉTABLI.\n";
    exit(0);
}

echo "Compte GAMAD P1 : NON ÉTABLI ({$echecs} écart(s)).\n";
exit(1);
