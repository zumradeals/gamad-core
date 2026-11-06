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
$invitationConnexion = $requete('GET', '/reseau/mon-compte');
$verifier(
    $invitationConnexion['statut'] === 302
        && str_ends_with($invitationConnexion['location'] ?? '', '/reseau/connexion'),
    'Réseau exige une session Core et présente sa propre connexion',
);
$connexionWeb = $requete('POST', '/reseau/connexion', [
    'entite' => 'personne.test+satellite@example.test',
    'secret' => $motDePasse,
], cookies: $invitationConnexion['cookies']);
$compteReseau = $requete('GET', '/reseau/mon-compte', cookies: $connexionWeb['cookies']);
$verifier(
    $connexionWeb['statut'] === 302
        && str_ends_with($connexionWeb['location'] ?? '', '/reseau/mon-compte')
        && $compteReseau['statut'] === 200
        && ($compteReseau['corps']['compte'] ?? null) === [
            'identite_reference' => $reference,
            'nom' => 'Personne Compte GAMAD',
            'bio' => null,
        ],
    'Réseau lit directement le compte et son nom Core avec la session du navigateur',
);
$cookies = $connexionWeb['cookies'];
$agir = $requete('GET', '/agir', cookies: $cookies);
$formulaireSavoir = $requete('GET', '/fil?type=knowledge', cookies: $cookies);
$verifier(
    $agir['statut'] === 200 && str_contains($agir['html'], '/fil?type=knowledge#publier')
        && $formulaireSavoir['statut'] === 200
        && str_contains($formulaireSavoir['html'], 'value="knowledge" selected'),
    'Agir ouvre le formulaire de publication avec le type choisi dans la session Core',
);
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
    'titre' => 'Technicien solaire', 'introduction' => 'Installation et dépannage.',
    'territoire' => 'ci-bouake', 'domaines' => ['artisanat', 'construction-et-industries'],
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
$experience = ['titre' => 'Chantier solaire déclaré', 'type' => 'professional', 'periode' => '2025', 'description' => 'Installation de panneaux.'];
$creationExperience = $requete('POST', '/mon-profil/experiences', $experience, cookies: $cookies);
$referenceExperience = $registreIdentites->query('SELECT reference FROM experience_profil_gamad')->fetchColumn();
$uriExperience = '/mon-profil/experiences/'.$referenceExperience;
$verifier(
    $creationExperience['statut'] === 302 && is_string($referenceExperience)
        && str_contains($requete('GET', $uriFiche)['html'], 'Chantier solaire déclaré')
        && $requete('PATCH', $uriExperience, array_replace($experience, ['titre' => 'Installation solaire déclarée']), cookies: $cookies)['statut'] === 302
        && str_contains($requete('GET', $uriFiche)['html'], 'Installation solaire déclarée')
        && !str_contains($requete('GET', '/personnes')['html'], 'Installation solaire déclarée'),
    'le membre ajoute et modifie une expérience qui apparaît uniquement dans sa fiche',
);
$annuaireFiltre = '/personnes?territoire=ci-bouake&domaine=artisanat&competence=apportees&q=electricite';
$ficheStructuree = $requete('GET', $uriFiche);
$verifier(
    str_contains($requete('GET', $annuaireFiltre)['html'], 'Technicien solaire')
        && str_contains($ficheStructuree['html'], 'Installation et dépannage.')
        && str_contains($ficheStructuree['html'], 'Bouaké')
        && !str_contains($requete('GET', str_replace('ci-bouake', 'ci-abidjan', $annuaireFiltre))['html'], 'Technicien solaire')
        && !str_contains($requete('GET', str_replace('apportees', 'recherchees', $annuaireFiltre))['html'], 'Technicien solaire'),
    'le profil structuré est consultable et les trois filtres d’annuaire se combinent',
);
$requete('PATCH', '/mon-profil', ['bio' => 'Profil pour annuaire', 'visibilite' => 'members', 'apportees' => 'Électricité'], cookies: $cookies);
$verifier(
    !str_contains($requete('GET', $annuaireFiltre)['html'], 'Technicien solaire')
        && str_contains($requete('GET', $annuaireFiltre, cookies: $cookies)['html'], 'Technicien solaire')
        && $requete('GET', $uriFiche)['statut'] === 404
        && str_contains($requete('GET', $uriFiche, cookies: $cookies)['html'], 'Installation solaire déclarée'),
    'les filtres respectent l’audience du profil et une ancienne mise à jour conserve les détails structurés',
);
$requete('PATCH', '/mon-profil', ['bio' => 'Profil pour annuaire', 'visibilite' => 'public', 'apportees' => 'Électricité'], cookies: $cookies);
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
$souhait = $requete('POST', '/fil', [
    'type' => 'learning_wish', 'visibilite' => 'members', 'contenu' => 'Apprendre à installer des panneaux solaires.',
], cookies: $cookies);
$offre = $requete('POST', '/fil', [
    'type' => 'offer', 'visibilite' => 'public', 'contenu' => 'Proposer un accompagnement en électricité.',
], cookies: $cookies);
$uriSouhait = parse_url($souhait['location'] ?? '', PHP_URL_PATH) ?: '/publication-manquante';
$uriOffre = parse_url($offre['location'] ?? '', PHP_URL_PATH) ?: '/publication-manquante';
$lectureOffre = $requete('GET', $uriOffre);
$verifier(
    $souhait['statut'] === 302 && $offre['statut'] === 302
        && $requete('GET', $uriSouhait)['statut'] === 404
        && str_contains($requete('GET', $uriSouhait, cookies: $cookies)['html'], 'Je veux apprendre')
        && $lectureOffre['statut'] === 200 && str_contains($lectureOffre['html'], 'Je propose mon aide')
        && str_contains($requete('GET', '/fil', cookies: $cookies)['html'], $uriOffre),
    'le fil et la fiche prennent en charge souhait d’apprendre et offre d’aide avec leur audience',
);
$realisationPreuve = $requete('POST', '/fil', [
    'type' => 'personal_work', 'visibilite' => 'members', 'contenu' => 'Réalisation jointe à mon expérience.',
], cookies: $cookies);
$uriRealisationPreuve = parse_url($realisationPreuve['location'] ?? '', PHP_URL_PATH) ?: '/publication-manquante';
$uriPreuveExperience = $uriExperience.'/preuve';
$verifier(
    $realisationPreuve['statut'] === 302
        && $requete('PUT', $uriPreuveExperience, ['publication' => basename($uriOffre)], cookies: $cookies)['statut'] === 404
        && $requete('PUT', $uriPreuveExperience, ['publication' => basename($uriRealisationPreuve)], cookies: $cookies)['statut'] === 302
        && !str_contains($requete('GET', $uriFiche)['html'], $uriRealisationPreuve)
        && str_contains($requete('GET', $uriFiche, cookies: $cookies)['html'], $uriRealisationPreuve),
    'la preuve est une réalisation personnelle et suit sa propre audience dans le profil',
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
$commentaires = $registreIdentites->prepare('SELECT reference FROM commentaire_gamad WHERE publication_reference = ?');
$commentaires->execute([basename($uriPublication)]);
$uriModificationCommentaire = $uriPublication.'/commentaires/'.$commentaires->fetchColumn();
$commentaires->closeCursor();
$verifier(
    $requete('PATCH', $uriModificationCommentaire, ['contenu' => 'Modification par un tiers'], cookies: $secondCookies)['statut'] === 404
        && $requete('PATCH', $uriPrivee.'/commentaires/'.basename($uriModificationCommentaire), ['contenu' => 'Mauvaise publication'], cookies: $premierCookies)['statut'] === 404
        && $requete('PATCH', $uriModificationCommentaire, ['contenu' => 'Commentaire corrigé.'], cookies: $premierCookies)['statut'] === 204
        && str_contains($requete('GET', $uriPublication)['html'], 'Commentaire corrigé.'),
    'seul l’auteur Core peut corriger son commentaire sur la publication correspondante',
);
$parentCommentaire = basename($uriModificationCommentaire);
$uriCommentaires = $uriPublication.'/commentaires';
$reponseCommentaire = $requete('POST', $uriCommentaires, [
    'parent' => $parentCommentaire, 'contenu' => 'Une réponse au commentaire.',
], cookies: $secondCookies);
$commentairesPublics = $requete('GET', $uriCommentaires);
$reponsesCommentaire = array_values(array_filter($commentairesPublics['corps'], static fn (array $ligne): bool => $ligne['parent_reference'] !== null));
$verifier(
    $reponseCommentaire['statut'] === 302 && $commentairesPublics['statut'] === 200
        && ($reponsesCommentaire[0]['parent_reference'] ?? null) === $parentCommentaire
        && ($reponsesCommentaire[0]['contenu'] ?? null) === 'Une réponse au commentaire.'
        && $requete('POST', $uriCommentaires, ['parent' => $reponsesCommentaire[0]['reference'] ?? '', 'contenu' => 'Trop profond.'], cookies: $secondCookies)['statut'] === 404
        && $requete('POST', $uriPrivee.'/commentaires', ['parent' => $parentCommentaire, 'contenu' => 'Mauvais fil.'], cookies: $secondCookies)['statut'] === 404
        && $requete('GET', $uriPrivee.'/commentaires')['statut'] === 404,
    'les réponses restent dans leur discussion, sur un seul niveau et dans l’audience de la publication',
);
$retraitTiers = $requete('DELETE', $uriModificationCommentaire, cookies: $secondCookies);
$retraitAuteur = $requete('DELETE', $uriModificationCommentaire, cookies: $premierCookies);
$filApresRetrait = $requete('GET', $uriCommentaires);
$ficheApresRetrait = $requete('GET', $uriPublication, cookies: $secondCookies);
$parReference = array_column($filApresRetrait['corps'], null, 'reference');
$texteRetire = $registreIdentites->prepare('SELECT contenu FROM commentaire_gamad WHERE reference = ?');
$texteRetire->execute([$parentCommentaire]);
$contenuRetire = $texteRetire->fetchColumn();
$texteRetire->closeCursor();
$verifier(
    $retraitTiers['statut'] === 404 && $retraitAuteur['statut'] === 204
        && isset($parReference[$parentCommentaire]['retire_le'])
        && $parReference[$parentCommentaire]['contenu'] === null
        && $ficheApresRetrait['statut'] === 200
        && preg_match('/<article id="commentaire-'.$parentCommentaire.'">.*?Commentaire retiré\..*?<ul[^>]*>.*?Une réponse au commentaire\..*?<\/article>/s', $ficheApresRetrait['html']) === 1
        && !str_contains($ficheApresRetrait['html'], 'Commentaire corrigé.')
        && !str_contains($ficheApresRetrait['html'], 'name="parent" value="'.$parentCommentaire.'"')
        && $contenuRetire === 'Commentaire retiré.'
        && ($parReference[$reponsesCommentaire[0]['reference']]['contenu'] ?? null) === 'Une réponse au commentaire.'
        && $requete('PATCH', $uriModificationCommentaire, ['contenu' => 'Rétablir.'], cookies: $premierCookies)['statut'] === 404
        && $requete('POST', $uriCommentaires, ['parent' => $parentCommentaire, 'contenu' => 'Trop tard.'], cookies: $secondCookies)['statut'] === 404
        && $requete('POST', $uriPublication.'/signalements/'.$parentCommentaire, ['motif' => 'spam'], cookies: $secondCookies)['statut'] === 404,
    'seul l’auteur retire son commentaire, le texte est effacé et les réponses restent consultables',
);
$besoinPrive = $requete('POST', '/besoins', [
    'visibilite' => 'members', 'contenu' => 'Besoin réservé aux membres.',
    'domaine' => 'artisanat', 'territoire' => 'ci-bouake',
], cookies: $premierCookies);
$uriBesoinPrive = '/besoins/'.($besoinPrive['corps']['reference'] ?? '');
$besoinPublic = $requete('POST', '/besoins', [
    'visibilite' => 'public', 'contenu' => 'Besoin ouvert au public.', 'auteur_reference' => $referenceTel,
], cookies: $premierCookies);
$uriBesoinPublic = '/besoins/'.($besoinPublic['corps']['reference'] ?? '');
$verifier(
    $besoinPrive['statut'] === 201 && $besoinPublic['statut'] === 201
        && $requete('GET', $uriBesoinPrive)['statut'] === 404
        && ($requete('GET', $uriBesoinPrive, cookies: $secondCookies)['corps']['etat_besoin'] ?? null) === 'open'
        && ($requete('GET', $uriBesoinPublic)['corps']['auteur'] ?? null) === 'Personne Compte GAMAD'
        && $requete('PATCH', $uriBesoinPublic.'/etat', ['action' => 'resoudre'], cookies: $secondCookies)['statut'] === 404,
    'un besoin naît ouvert sous l’identité Core de la session et respecte son audience',
);
$verifier(
    $requete('PATCH', $uriBesoinPublic.'/etat', ['action' => 'resoudre'], cookies: $premierCookies)['statut'] === 204
        && $requete('PATCH', $uriBesoinPublic.'/etat', ['action' => 'fermer'], cookies: $premierCookies)['statut'] === 404
        && ($requete('GET', $uriBesoinPublic)['corps']['etat_besoin'] ?? null) === 'resolved'
        && $requete('PATCH', $uriBesoinPrive.'/etat', ['action' => 'fermer'], cookies: $premierCookies)['statut'] === 204
        && ($requete('GET', $uriBesoinPrive, cookies: $premierCookies)['corps']['etat_besoin'] ?? null) === 'closed',
    'l’auteur résout ou ferme son besoin une seule fois',
);
$besoinAide = $requete('POST', '/besoins', [
    'visibilite' => 'public', 'contenu' => 'Besoin avec une proposition d’aide.',
    'domaine' => 'artisanat', 'territoire' => 'ci-abidjan',
], cookies: $premierCookies);
$uriBesoinAide = '/besoins/'.($besoinAide['corps']['reference'] ?? '');
$uriPropositions = $uriBesoinAide.'/propositions';
$proposition = $requete('POST', $uriPropositions, ['message' => 'Je peux aider.'], cookies: $secondCookies);
$uriProposition = $uriPropositions.'/'.($proposition['corps']['reference'] ?? '');
$verifier(
    $proposition['statut'] === 201
        && $requete('POST', $uriPropositions, [], cookies: $premierCookies)['statut'] === 403
        && $requete('POST', $uriPropositions, [], cookies: $secondCookies)['statut'] === 409
        && ($requete('GET', $uriPropositions, cookies: $premierCookies)['corps'][0]['message'] ?? null) === 'Je peux aider.'
        && $requete('PATCH', $uriProposition, ['action' => 'accepter'], cookies: $secondCookies)['statut'] === 404
        && $requete('PATCH', $uriProposition, ['action' => 'retirer'], cookies: $secondCookies)['statut'] === 204,
    'une proposition est unique, visible par l’auteur et retirable uniquement par son proposant',
);
$nouvelleAide = $requete('POST', $uriPropositions, [], cookies: $secondCookies);
$uriNouvelleAide = $uriPropositions.'/'.($nouvelleAide['corps']['reference'] ?? '');
$uriMessagesAide = $uriNouvelleAide.'/messages';
$avantAcceptationAide = $requete('POST', $uriMessagesAide, ['texte' => 'Trop tôt'], cookies: $secondCookies);
$verifier(
    $nouvelleAide['statut'] === 201
        && $requete('PATCH', $uriProposition, ['action' => 'accepter'], cookies: $premierCookies)['statut'] === 404
        && $requete('PATCH', $uriNouvelleAide, ['action' => 'accepter'], cookies: $premierCookies)['statut'] === 204
        && $requete('PATCH', $uriNouvelleAide, ['action' => 'retirer'], cookies: $secondCookies)['statut'] === 409
        && ($requete('GET', $uriBesoinAide)['corps']['etat_besoin'] ?? null) === 'in_progress'
        && $requete('POST', $uriBesoinPublic.'/propositions', [], cookies: $secondCookies)['statut'] === 409,
    'une proposition acceptée fait passer le besoin en cours et les besoins clos refusent toute nouvelle proposition',
);
$messageAide = $requete('POST', $uriMessagesAide, ['texte' => 'Organisons cette aide.'], cookies: $secondCookies);
$lectureAide = $requete('GET', $uriMessagesAide, cookies: $premierCookies);
$lectureProposant = $requete('GET', $uriMessagesAide, cookies: $secondCookies);
$verifier(
    $avantAcceptationAide['statut'] === 404 && $messageAide['statut'] === 204
        && $lectureAide['statut'] === 200 && count($lectureAide['corps']) === 1
        && ($lectureAide['corps'][0]['texte'] ?? null) === 'Organisons cette aide.'
        && ($lectureAide['corps'][0]['de_moi'] ?? null) === false
        && ($lectureProposant['corps'][0]['de_moi'] ?? null) === true
        && $requete('GET', $uriMessagesAide)['statut'] === 302
        && $requete('GET', $uriBesoinPublic.'/propositions/'.basename($uriNouvelleAide).'/messages', cookies: $premierCookies)['statut'] === 404
        && (int) $registreIdentites->query('SELECT COUNT(*) FROM relation_personnes_gamad')->fetchColumn() === 0,
    'la conversation naît avec l’acceptation et reste propre à la proposition, sans créer de relation',
);
$cataloguePublic = $requete('GET', '/besoins?etat=tous');
$catalogueMembre = $requete('GET', '/besoins?etat=closed&q=RESERVE', cookies: $secondCookies);
$catalogueEnCours = $requete('GET', '/besoins?etat=in_progress&q=PROPOSITION');
$verifier(
    $cataloguePublic['statut'] === 200
        && !in_array(basename($uriBesoinPrive), array_column($cataloguePublic['corps']['besoins'], 'reference'), true)
        && ($catalogueMembre['corps']['besoins'][0]['reference'] ?? null) === basename($uriBesoinPrive)
        && ($catalogueEnCours['corps']['besoins'][0]['reference'] ?? null) === basename($uriBesoinAide)
        && ($requete('GET', '/besoins')['corps']['besoins'] ?? null) === []
        && ($requete('GET', '/besoins?etat=tous&page=2')['corps']['besoins'] ?? null) === [],
    'le catalogue filtre les besoins par état et par texte et respecte la visibilité avant pagination',
);
$referentielsBesoins = $requete('GET', '/besoins/referentiels');
$catalogueClasse = $requete('GET', '/besoins?etat=tous&domaine=artisanat&territoire=ci-abidjan');
$verifier(
    ($referentielsBesoins['corps']['domaines']['artisanat'] ?? null) === 'Artisanat'
        && ($referentielsBesoins['corps']['territoires']['ci-bouake'] ?? null) === 'Bouaké'
        && array_column($catalogueClasse['corps']['besoins'] ?? [], 'reference') === [basename($uriBesoinAide)]
        && ($requete('GET', $uriBesoinAide)['corps']['territoire'] ?? null) === 'ci-abidjan'
        && ($requete('GET', '/besoins?etat=tous&domaine=artisanat&territoire=ci-bouake')['corps']['besoins'] ?? null) === []
        && ($requete('GET', '/besoins?etat=tous&domaine=artisanat&territoire=ci-bouake', cookies: $secondCookies)['corps']['besoins'][0]['reference'] ?? null) === basename($uriBesoinPrive)
        && $requete('GET', '/besoins?domaine=inconnu')['statut'] === 302
        && $requete('POST', '/besoins', ['visibilite' => 'public', 'contenu' => 'Invalide', 'territoire' => 'inconnu'], cookies: $premierCookies)['statut'] === 302,
    'le classement territorial et sectoriel utilise des codes de référence et conserve la visibilité',
);
$notificationsAide = $requete('GET', '/notifications/donnees', cookies: $premierCookies)['corps'];
$notificationsProposant = $requete('GET', '/notifications/donnees', cookies: $secondCookies)['corps'];
$typesAide = array_column($notificationsAide, 'type');
$typesProposant = array_column($notificationsProposant, 'type');
$acceptationAide = array_values(array_filter($notificationsProposant, static fn (array $notice): bool => $notice['type'] === 'aide_acceptee'))[0] ?? [];
$lectureNoticeAide = '/notifications/'.($acceptationAide['reference'] ?? '').'/lire';
$verifier(
    count(array_keys($typesAide, 'aide_proposee', true)) === 1
        && count(array_keys($typesAide, 'message_aide', true)) === 1
        && !in_array('aide_acceptee', $typesAide, true)
        && count(array_keys($typesProposant, 'aide_acceptee', true)) === 1
        && ($acceptationAide['proposition_reference'] ?? null) === basename($uriNouvelleAide)
        && ($acceptationAide['besoin_reference'] ?? null) === basename($uriBesoinAide)
        && !isset($acceptationAide['auteur_reference'])
        && $requete('POST', $lectureNoticeAide, cookies: $premierCookies)['statut'] === 404
        && $requete('POST', $lectureNoticeAide, cookies: $secondCookies)['statut'] === 302,
    'les notifications d’aide sont privées, contextualisées et sans doublon après un retrait et une nouvelle proposition',
);
$uriReponsesSouhait = $uriSouhait.'/reponses';
$uriReponsesOffre = $uriOffre.'/reponses';
$accompagnement = $requete('POST', $uriReponsesSouhait, ['message' => 'Je peux vous accompagner.'], cookies: $secondCookies);
$interet = $requete('POST', $uriReponsesOffre, [], cookies: $secondCookies);
$uriAccompagnement = $uriReponsesSouhait.'/'.($accompagnement['corps']['reference'] ?? '');
$uriInteret = $uriReponsesOffre.'/'.($interet['corps']['reference'] ?? '');
$verifier(
    $accompagnement['statut'] === 201 && $interet['statut'] === 201
        && $requete('POST', $uriReponsesSouhait, [], cookies: $secondCookies)['statut'] === 409
        && $requete('POST', $uriPublication.'/reponses', [], cookies: $secondCookies)['statut'] === 404
        && $requete('POST', '/besoins/'.basename($uriOffre).'/propositions', [], cookies: $secondCookies)['statut'] === 404
        && $requete('PATCH', $uriAccompagnement, ['action' => 'accepter'], cookies: $secondCookies)['statut'] === 404
        && $requete('POST', $uriAccompagnement.'/messages', ['texte' => 'Trop tôt.'], cookies: $secondCookies)['statut'] === 404
        && $requete('PATCH', $uriAccompagnement, ['action' => 'accepter'], cookies: $premierCookies)['statut'] === 204
        && $requete('PATCH', $uriInteret, ['action' => 'refuser'], cookies: $premierCookies)['statut'] === 204,
    'les deux réponses du fil sont uniques et l’auteur décide sans modifier le cycle des besoins',
);
$verifier(
    $requete('POST', $uriAccompagnement.'/messages', ['texte' => 'Préparons la séance.'], cookies: $secondCookies)['statut'] === 204
        && ($requete('GET', $uriAccompagnement.'/messages', cookies: $premierCookies)['corps'][0]['texte'] ?? null) === 'Préparons la séance.'
        && $requete('GET', $uriInteret.'/messages', cookies: $secondCookies)['statut'] === 404
        && in_array('reponse_acceptee', array_column($requete('GET', '/notifications/donnees', cookies: $secondCookies)['corps'], 'type'), true)
        && in_array('message_reponse', array_column($requete('GET', '/notifications/donnees', cookies: $premierCookies)['corps'], 'type'), true),
    'seule la réponse acceptée ouvre une conversation privée et les notifications sont contextualisées',
);
$verifier(
    $requete('PUT', $uriPreuveExperience, ['publication' => basename($uriRealisationPreuve)], cookies: $secondCookies)['statut'] === 404
        && $requete('DELETE', $uriPreuveExperience, cookies: $secondCookies)['statut'] === 404
        && $requete('DELETE', $uriPreuveExperience, cookies: $premierCookies)['statut'] === 302
        && !str_contains($requete('GET', $uriFiche, cookies: $secondCookies)['html'], $uriRealisationPreuve)
        && $requete('DELETE', $uriExperience, cookies: $secondCookies)['statut'] === 404
        && $requete('DELETE', $uriExperience, cookies: $premierCookies)['statut'] === 302
        && !str_contains($requete('GET', $uriFiche)['html'], 'Installation solaire déclarée'),
    'seul le propriétaire peut retirer une expérience',
);
$referenceSecond = $registreIdentites->prepare('SELECT reference_publique FROM fiche_annuaire_gamad WHERE identite_reference = ?');
$referenceSecond->execute([$referenceTel]);
$uriSecond = '/personnes/'.$referenceSecond->fetchColumn();
$referenceSecond->closeCursor();
$uriBlocage = '/mon-profil/blocages/'.$fichePublique;
$relationVersPremier = '/relations/'.$fichePublique;
$relationVersSecond = '/relations/'.basename($uriSecond);
$uriSuivi = $uriFiche.'/suivi';
$uriSuiviInverse = $uriSecond.'/suivi';
$uriListeSuivis = '/mon-profil/suivis';
$verifier(
    $requete('PUT', $uriSuivi, cookies: $secondCookies)['statut'] === 204
        && $requete('PUT', $uriSuivi, cookies: $secondCookies)['statut'] === 204
        && array_column($requete('GET', $uriListeSuivis, cookies: $secondCookies)['corps'], 'reference_publique') === [$fichePublique]
        && $requete('GET', $uriListeSuivis, cookies: $premierCookies)['corps'] === []
        && $requete('PUT', $uriSuivi, cookies: $premierCookies)['statut'] === 404
        && $requete('DELETE', $uriSuivi, cookies: $secondCookies)['statut'] === 204
        && $requete('GET', $uriListeSuivis, cookies: $secondCookies)['corps'] === []
        && $requete('PUT', $uriSuivi, cookies: $secondCookies)['statut'] === 204
        && $requete('PUT', $uriSuiviInverse, cookies: $premierCookies)['statut'] === 204
        && (int) $registreIdentites->query('SELECT COUNT(*) FROM relation_personnes_gamad')->fetchColumn() === 0,
    'le suivi est unilatéral, privé, idempotent et distinct de la relation',
);
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
$uriAttestations = $uriRealisationPreuve.'/attestations';
$demandeAttestation = ['profil' => basename($uriSecond), 'affirmation' => 'J’ai participé à cette installation.', 'realise_le' => '2025-05-01'];
$attestantFaible = $requete('POST', $uriAttestations, $demandeAttestation, cookies: $premierCookies);
$ctr01->inscrireEvenementAssurance($referenceTel, 'A2', 'TEST-ATTEST-A2', 'TEST');
$attestation = $requete('POST', $uriAttestations, $demandeAttestation, cookies: $premierCookies);
$uriDecisionAttestation = '/mon-profil/attestations/'.($attestation['corps']['reference'] ?? '');
$decisionAttestation = ['action' => 'confirmer', 'revision' => 1, 'role' => 'colleague', 'consentement' => true];
$verifier(
    $attestantFaible['statut'] === 403 && $attestation['statut'] === 201
        && ($requete('GET', '/mon-profil/attestations', cookies: $secondCookies)['corps'][0]['etat'] ?? null) === 'pending'
        && $requete('PATCH', $uriDecisionAttestation, $decisionAttestation, cookies: $premierCookies)['statut'] === 404
        && $requete('PATCH', $uriDecisionAttestation, array_diff_key($decisionAttestation, ['consentement' => true]), cookies: $secondCookies)['statut'] === 302
        && $requete('PATCH', $uriDecisionAttestation, $decisionAttestation, cookies: $secondCookies)['statut'] === 204
        && $requete('PATCH', $uriDecisionAttestation, $decisionAttestation, cookies: $secondCookies)['statut'] === 409
        && $requete('GET', $uriAttestations)['statut'] === 404
        && ($requete('GET', $uriAttestations, cookies: $premierCookies)['corps'][0]['role'] ?? null) === 'colleague',
    'l’attestation exige une relation acceptée, l’assurance Core et le consentement explicite du tiers',
);
$ctr01->inscrireEvenementAssurance($referenceTel, 'A2', 'TEST-ATTEST-A2-REVISE', 'TEST');
$verifier(
    $requete('GET', $uriAttestations, cookies: $premierCookies)['corps'] === []
        && $requete('PATCH', $uriDecisionAttestation, array_replace($decisionAttestation, ['revision' => 2]), cookies: $secondCookies)['statut'] === 204
        && count($requete('GET', $uriAttestations, cookies: $premierCookies)['corps']) === 1,
    'une révision d’assurance suspend l’affichage jusqu’au renouvellement du consentement',
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
$requete('POST', $uriModeration.'/commentaires', ['contenu' => 'Commentaire avant masquage.'], cookies: $premierCookies);
$commentaireModere = $registreIdentites->prepare('SELECT reference FROM commentaire_gamad WHERE publication_reference = ?');
$commentaireModere->execute([basename($uriModeration)]);
$uriCommentaireModere = $uriModeration.'/commentaires/'.$commentaireModere->fetchColumn();
$commentaireModere->closeCursor();
$signalement = $requete('POST', $uriModeration.'/signalements', ['motif' => 'spam'], cookies: $secondCookies);
$signalements = $registreIdentites->query('SELECT reference FROM signalement_reseau_gamad');
$uriDecision = '/console/moderation-reseau/'.$signalements->fetchColumn();
$signalements->closeCursor();
$nonAdmin = $requete('POST', $uriDecision, ['action' => 'masquer'], cookies: $secondCookies);
$admission = $console->call('core:console:accorder-acces-administratif');
$secretModeration = 'Secret-moderation-GAMAD-2026';
$ctr16->inscrireAuthentificateur($autorite, $secretModeration);
$admin = $requete('POST', '/connexion', ['entite' => $autorite, 'secret' => $secretModeration]);
$fileAttestations = '/console/moderation-attestations';
$uriModerationAttestation = $fileAttestations.'/'.basename($uriDecisionAttestation);
$revocationAttestation = ['action' => 'revoquer', 'revision' => 3, 'motif' => 'Vérification complémentaire nécessaire.'];
$verifier(
    $requete('GET', $fileAttestations, cookies: $premierCookies)['statut'] === 403
        && $requete('PATCH', $uriModerationAttestation, $revocationAttestation, cookies: $premierCookies)['statut'] === 403
        && $requete('PATCH', $uriModerationAttestation, $revocationAttestation, cookies: $admin['cookies'])['statut'] === 204
        && $requete('GET', $uriAttestations, cookies: $premierCookies)['corps'] === []
        && ($requete('GET', '/mon-profil/attestations', cookies: $secondCookies)['corps'][0]['etat'] ?? null) === 'revoked',
    'la révocation relève de la console et retire immédiatement l’attestation de la publication',
);
$reexamenAttestation = ['action' => 'reexaminer', 'revision' => 4, 'motif' => 'Le recours justifie un réexamen.'];
$recoursAttestation = ['revision' => 4, 'motif' => 'Je demande de revoir la décision.'];
$verifier(
    $requete('PATCH', $uriModerationAttestation, $reexamenAttestation, cookies: $admin['cookies'])['statut'] === 409
        && $requete('POST', $uriDecisionAttestation.'/recours', $recoursAttestation, cookies: $admin['cookies'])['statut'] === 404
        && $requete('POST', $uriDecisionAttestation.'/recours', $recoursAttestation, cookies: $secondCookies)['statut'] === 204
        && ($requete('GET', $fileAttestations, cookies: $admin['cookies'])['corps'][0]['recours'] ?? null) === $recoursAttestation['motif']
        && $requete('PATCH', $uriModerationAttestation, array_replace($reexamenAttestation, ['revision' => 5]), cookies: $admin['cookies'])['statut'] === 204
        && $requete('GET', $uriAttestations, cookies: $premierCookies)['corps'] === []
        && $requete('PATCH', $uriDecisionAttestation, array_replace($decisionAttestation, ['revision' => 6]), cookies: $secondCookies)['statut'] === 204
        && count($requete('GET', $uriAttestations, cookies: $premierCookies)['corps']) === 1
        && $requete('PATCH', $uriDecisionAttestation, ['action' => 'retirer', 'revision' => 7], cookies: $premierCookies)['statut'] === 204
        && $requete('GET', $uriAttestations, cookies: $premierCookies)['corps'] === []
        && $registreIdentites->query('SELECT COUNT(*) FROM evenement_moderation_attestation_gamad')->fetchColumn() === 3,
    'le recours d’un participant ouvre un réexamen journalisé et exige un nouveau consentement avant publication',
);
$ecranModeration = $requete('GET', '/console/moderation-reseau', cookies: $admin['cookies']);
$decision = $requete('POST', $uriDecision, ['action' => 'masquer'], cookies: $admin['cookies']);
$verifier(
    $signalement['statut'] === 302 && $nonAdmin['statut'] === 403
        && $admission === 0 && str_contains($ecranModeration['html'], 'Contenu de test à modérer.')
        && $decision['statut'] === 302 && $requete('GET', $uriModeration)['statut'] === 404,
    'seule la console Core traite le signalement et le masquage retire l’URL publique',
);
$verifier(
    $requete('PATCH', $uriCommentaireModere, ['contenu' => 'Modification après masquage'], cookies: $premierCookies)['statut'] === 404,
    'le masquage d’une publication ferme aussi la modification des commentaires',
);
$uriFavoris = '/mon-profil/favoris-publications';
$uriFavori = $uriPublication.'/favori';
$verifier(
    $requete('PUT', $uriFavori, cookies: $secondCookies)['statut'] === 204
        && $requete('PUT', $uriFavori, cookies: $secondCookies)['statut'] === 204
        && array_column($requete('GET', $uriFavoris, cookies: $secondCookies)['corps'], 'reference') === [basename($uriPublication)]
        && $requete('GET', $uriFavoris, cookies: $premierCookies)['corps'] === [],
    'un favori est privé et son ajout est idempotent',
);
$avantBlocage = $requete('GET', $uriPublication, cookies: $secondCookies);
$blocage = $requete('POST', $uriBlocage, cookies: $secondCookies);
$listeBlocages = $requete('GET', '/mon-profil/blocages', cookies: $secondCookies);
$verifier(
    $requete('GET', $uriListeSuivis, cookies: $secondCookies)['corps'] === []
        && $requete('GET', $uriListeSuivis, cookies: $premierCookies)['corps'] === []
        && (int) $registreIdentites->query('SELECT COUNT(*) FROM suivi_personne_gamad')->fetchColumn() === 0
        && $requete('PUT', $uriSuiviInverse, cookies: $premierCookies)['statut'] === 404,
    'un blocage retire définitivement les deux suivis et empêche de suivre à nouveau',
);
$verifier(
    $requete('GET', $uriFavoris, cookies: $secondCookies)['corps'] === []
        && $requete('PUT', $uriFavori, cookies: $secondCookies)['statut'] === 404
        && $requete('DELETE', $uriFavori, cookies: $secondCookies)['statut'] === 204,
    'un blocage masque le favori et permet de le retirer sans rouvrir la publication',
);
$verifier(
    $requete('GET', $uriMessagesAide, cookies: $premierCookies)['statut'] === 404
        && $requete('POST', $uriMessagesAide, ['texte' => 'Après blocage'], cookies: $secondCookies)['statut'] === 404,
    'le blocage ferme la lecture et l’envoi dans les deux sens',
);
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
$modificationProjet = ['titre' => 'Projet corrigé', 'dossier' => 'Dossier privé corrigé.'];
$verifier(
    $requete('PATCH', $uriProjet, $modificationProjet, cookies: $premierCookies)['statut'] === 404
        && $requete('PATCH', $uriProjet, $modificationProjet, cookies: $secondCookies)['statut'] === 204
        && str_contains($requete('GET', $uriProjet, cookies: $secondCookies)['html'], 'Dossier privé corrigé.')
        && !str_contains($requete('GET', $uriZumra, cookies: $premierCookies)['html'], 'Dossier privé corrigé.'),
    'seul le porteur Core peut modifier le dossier et la confidentialité demeure',
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
        && !str_contains($vitrine['html'], 'Dossier privé corrigé.')
        && str_contains($requete('GET', '/projets')['html'], $uriProjet),
    'la vitrine devient publique seulement après accueil accepté et publication par le porteur',
);
$besoinZumra = ['visibilite' => 'public', 'contenu' => 'Besoin de la ZUMRA.', 'zumra' => basename($uriZumra)];
$besoinProjet = ['visibilite' => 'public', 'contenu' => 'Besoin du projet.', 'projet' => basename($uriProjet)];
$publicationZumra = $requete('POST', '/besoins', $besoinZumra, cookies: $premierCookies);
$publicationProjet = $requete('POST', '/besoins', $besoinProjet, cookies: $secondCookies);
$referenceBesoinProjet = $publicationProjet['corps']['reference'] ?? '';
$interneZumra = $requete('POST', '/besoins', array_replace($besoinZumra, ['visibilite' => 'zumra']), cookies: $premierCookies);
$uriInterneZumra = '/besoins/'.($interneZumra['corps']['reference'] ?? '');
$verifier(
    $publicationZumra['statut'] === 201 && $publicationProjet['statut'] === 201
        && $requete('POST', '/besoins', $besoinZumra, cookies: $secondCookies)['statut'] === 404
        && $requete('POST', '/besoins', $besoinProjet, cookies: $premierCookies)['statut'] === 404
        && array_column($requete('GET', '/besoins?emetteur=zumra')['corps']['besoins'] ?? [], 'reference') === [$publicationZumra['corps']['reference']]
        && array_column($requete('GET', '/besoins?emetteur=projet')['corps']['besoins'] ?? [], 'reference') === [$referenceBesoinProjet]
        && ($requete('GET', '/besoins/'.$referenceBesoinProjet)['corps']['emetteur_reference'] ?? null) === basename($uriProjet)
        && $requete('PATCH', '/besoins/'.$referenceBesoinProjet.'/etat', ['action' => 'fermer'], cookies: $premierCookies)['statut'] === 404,
    'les besoins collectifs sont émis par les rôles autorisés et se filtrent par émetteur',
);
$verifier(
    $interneZumra['statut'] === 201
        && $requete('GET', $uriInterneZumra)['statut'] === 404
        && $requete('GET', $uriInterneZumra, cookies: $secondCookies)['statut'] === 404
        && $requete('GET', $uriInterneZumra, cookies: $premierCookies)['corps']['visibilite'] === 'zumra'
        && $requete('POST', $uriInterneZumra.'/propositions', [], cookies: $secondCookies)['statut'] === 404,
    'la visibilité interne ZUMRA écarte les visiteurs et les membres GAMAD extérieurs',
);
$requete('POST', $uriProjet.'/vitrine', ['action' => 'retirer'], cookies: $secondCookies);
$verifier(
    $requete('GET', '/besoins/'.$referenceBesoinProjet)['statut'] === 404
        && ($requete('GET', '/besoins?emetteur=projet')['corps']['besoins'] ?? null) === []
        && $requete('POST', '/besoins/'.$referenceBesoinProjet.'/propositions', [], cookies: $premierCookies)['statut'] === 404,
    'le retrait de la vitrine ferme le besoin collectif et les nouvelles propositions',
);
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
$besoinDelegue = $requete('POST', '/besoins', $besoinZumra, cookies: $secondCookies);
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
$verifier(
    $besoinDelegue['statut'] === 201
        && $requete('PATCH', '/besoins/'.($besoinDelegue['corps']['reference'] ?? '').'/etat', ['action' => 'fermer'], cookies: $secondCookies)['statut'] === 404,
    'la délégation autorise la publication pour la ZUMRA et sa révocation ferme les écritures',
);
$uriBesoinDelegue = '/besoins/'.($besoinDelegue['corps']['reference'] ?? '');
$verifier(
    $requete('GET', $uriInterneZumra, cookies: $secondCookies)['statut'] === 200
        && $requete('PATCH', $uriBesoinDelegue.'/etat', ['action' => 'resoudre'], cookies: $premierCookies)['statut'] === 204
        && $requete('PATCH', $uriBesoinDelegue.'/bilan', ['bilan' => 'Besoin résolu par la ZUMRA.'], cookies: $premierCookies)['statut'] === 204
        && ($requete('GET', $uriBesoinDelegue)['corps']['bilan'] ?? null) === 'Besoin résolu par la ZUMRA.',
    'le fondateur reprend le besoin après révocation et publie le bilan une fois le besoin résolu',
);
$uriBesoinZumra = '/besoins/'.$publicationZumra['corps']['reference'];
$aideZumra = $requete('POST', $uriBesoinZumra.'/propositions', [], cookies: $secondCookies);
$verifier(
    $aideZumra['statut'] === 201
        && $requete('PATCH', $uriBesoinZumra.'/etat', ['action' => 'fermer'], cookies: $premierCookies)['statut'] === 204
        && ($requete('GET', $uriBesoinZumra.'/propositions', cookies: $secondCookies)['corps'][0]['etat'] ?? null) === 'declined',
    'la clôture refuse les propositions encore en attente',
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
        && !str_contains($avantEquipe['html'], 'Dossier privé corrigé.')
        && $acceptationEquipe['statut'] === 302 && str_contains($apresEquipe['html'], 'Dossier privé corrigé.')
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
        && str_contains($collaborateurVoit['html'], 'Dossier privé corrigé.'),
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
