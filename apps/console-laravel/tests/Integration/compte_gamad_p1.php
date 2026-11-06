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
) use ($kernel): array {
    $serveur = [
        'HTTP_ACCEPT' => 'application/json',
        'CONTENT_TYPE' => 'application/json',
    ];
    if ($jeton !== null) {
        $serveur['HTTP_AUTHORIZATION'] = 'Bearer ' . $jeton;
    }
    $request = Request::create(
        $uri,
        $methode,
        [],
        $cookies,
        [],
        $serveur,
        $json === null ? null : json_encode($json, JSON_THROW_ON_ERROR),
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
$requete('DELETE', $uriBlocage, cookies: $secondCookies);
$verifier(
    $requete('GET', $uriPublication, cookies: $secondCookies)['statut'] === 200
        && $requete('GET', $uriSecond, cookies: $premierCookies)['statut'] === 200,
    'le déblocage rétablit la visibilité permise',
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
