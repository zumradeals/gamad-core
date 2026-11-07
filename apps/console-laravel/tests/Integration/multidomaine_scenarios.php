<?php

// Inclus dans le parcours produits : base jetable, produit fictif déclaré par le Core.
$app['config']->set('gamad_domains.enabled', true);
$app['config']->set('gamad_domains.institutional_host', 'console.gamad-afrique.com');
$app['config']->set('gamad_domains.trusted_proxies', ['10.42.0.1']);
$app['config']->set('session.domain', null);
\Illuminate\Http\Middleware\TrustProxies::at(['10.42.0.1']);
// Rétablissement de la fixture suspendue, sans bootstrap d'un produit réel.
$pdoProduits->exec("DELETE FROM produit_cycle WHERE produit_reference = 'PRD-APP-TEST-A' AND etat = 'SUSPENDU'");
$pdoProduits->exec("UPDATE produit_application SET domaine_public = 'reseau.gamad-afrique.com' WHERE produit_reference = 'PRD-APP-TEST-A'");
$httpDomaine = static function (string $host, string $path = '/', array $headers = [], string $method = 'GET') use (&$kernel): \Symfony\Component\HttpFoundation\Response {
    $req = \Illuminate\Http\Request::create('https://'.$host.$path, $method, [], [], [], array_replace([
        'HTTP_HOST' => $host, 'REMOTE_ADDR' => '198.51.100.5', 'HTTP_ACCEPT' => 'application/json',
    ], $headers));
    if (isset($headers['HTTP_HOST'])) {
        $req->headers->set('host', $headers['HTTP_HOST']);
    }
    $res = $kernel->handle($req);
    $kernel->terminate($req, $res);
    return $res;
};
$public = 'reseau.gamad-afrique.com';
$console = 'console.gamad-afrique.com';
$verifier($httpDomaine($public)->getStatusCode() === 200, 'HTTP domaine reconnu actif : accueil réel');
$verifier($httpDomaine('inconnu.gamad-afrique.com')->getStatusCode() === 404, 'HTTP domaine inconnu refusé');
$verifier($httpDomaine($console)->headers->get('Location') === 'https://'.$console.'/console', 'racine institutionnelle distincte sans destination client');
$verifier($httpDomaine($public, '/aide')->getStatusCode() === 200 && $httpDomaine($public, '/reseau/connexion')->getStatusCode() === 200, 'parcours publics et connexion Core préservés');
foreach (['/console', '/identites', '/produits', '/connexion', '/console/reseau', '/parametres/verification-comptes'] as $path) {
    $r = $httpDomaine($public, $path);
    $verifier($r->getStatusCode() === 404 && $r->headers->getCookies() === [], 'frontière avant session : '.$path);
}
$verifier($httpDomaine($console, '/connexion')->getStatusCode() === 200, 'connexion centrale conservée sur son domaine');
$verifier($httpDomaine($public, '/?application=autre&redirect=https://evil.com')->getStatusCode() === 200, 'paramètres navigateur ignorés pour la sélection applicative');
$verifier($httpDomaine($public, '/', ['HTTP_X_FORWARDED_HOST' => $console])->getStatusCode() === 400, 'proxy inconnu ne peut transmettre un hôte');
$verifier($httpDomaine($console, '/', ['REMOTE_ADDR' => '10.42.0.1', 'HTTP_X_FORWARDED_HOST' => $public])->getStatusCode() === 200, 'proxy explicitement approuvé transmet le domaine public');
$verifier($httpDomaine($console, '/identites', ['REMOTE_ADDR' => '10.42.0.1', 'HTTP_X_FORWARDED_HOST' => $public])->getStatusCode() === 404, 'proxy approuvé ne contourne pas la frontière publique');
foreach (['bad host', 'evil.com,'.$public, $public.':99999', $public.'@evil.com', $public.'/x'] as $bad) {
    $verifier($httpDomaine($public, '/', ['HTTP_HOST' => $bad])->getStatusCode() === 400, 'Host malformé refusé : '.$bad);
    $verifier($httpDomaine($console, '/', ['REMOTE_ADDR' => '10.42.0.1', 'HTTP_X_FORWARDED_HOST' => $bad])->getStatusCode() === 400, 'Forwarded-Host malformé refusé : '.$bad);
}
$verifier($httpDomaine($public.':443')->getStatusCode() === 200, 'Host avec port HTTPS reconnu');
$ancienEnv = $app['env'];
$app['env'] = 'production';
$verifier($httpDomaine($public, '/reseau/connexion', [], 'POST')->getStatusCode() === 419, 'CSRF réellement exigé sur POST web en production');
$verifier($httpDomaine($console, '/api/v1/produits', ['REMOTE_ADDR' => '10.42.0.1', 'HTTP_X_FORWARDED_HOST' => $public, 'HTTP_X_FORWARDED_PROTO' => 'http'])->getStatusCode() === 426, 'HTTPS API conservé derrière le proxy');
$app['env'] = $ancienEnv;
$r = $httpDomaine($public, '/espace');
$verifier($r->getStatusCode() === 302 && $r->headers->get('Location') === 'https://'.$public.'/reseau/connexion', 'liens et redirections restent sur le domaine effectif');
$verifier($r->headers->getCookies() !== [] && array_reduce($r->headers->getCookies(), fn ($ok, $cookie) => $ok && !$cookie->getDomain(), true), 'cookies réellement émis host-only');
$app['config']->set('session.domain', '.gamad-afrique.com');
$verifier($httpDomaine($public)->getStatusCode() === 503, 'configuration de cookie partagé refusée');
$app['config']->set('session.domain', null);
$app['config']->set('gamad_domains.trusted_proxies', ['*']);
$verifier($httpDomaine($public)->getStatusCode() === 503, 'confiance proxy globale interdite');
$app['config']->set('gamad_domains.trusted_proxies', ['10.42.0.1']);
$verifier($httpDomaine($public, '/api/v1/produits', ['HTTP_AUTHORIZATION' => 'Bearer '.$sessionAutorite])->getStatusCode() === 200, 'API existante reste authentifiée et utilisable');
$pdoProduits->exec("UPDATE produit_cycle SET etat = 'SUSPENDU' WHERE produit_reference = 'PRD-APP-TEST-A' AND etat = 'ACTIF'");
$verifier($httpDomaine($public)->getStatusCode() === 404, 'suspension prise en compte dès la requête suivante');
$pdoProduits->exec("UPDATE produit_cycle SET etat = 'RETIRE' WHERE produit_reference = 'PRD-APP-TEST-A' AND etat = 'SUSPENDU'");
$verifier($httpDomaine($public)->getStatusCode() === 404, 'produit retiré refusé');
$pdoProduits->exec("UPDATE produit_cycle SET etat = 'ACTIF' WHERE produit_reference = 'PRD-APP-TEST-A' AND etat = 'RETIRE'");
$reserve = (new \App\Application\Produits\AccesProduits())->declarerApplication('PRD-APP-TEST-A', array_replace($declaration, ['revision' => 2, 'domaine_public' => strtoupper($console).'.']), 'AUT-GAMAD-001');
$verifier($reserve['statut'] === 422, 'domaine institutionnel réservé même après normalisation');
// Validation réelle du cache : nouveau noyau lisant le fichier de routes compilé.
$consoleKernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$envDomaine = ['GAMAD_MULTIDOMAIN_ENABLED' => 'true', 'GAMAD_INSTITUTIONAL_HOST' => $console, 'TRUSTED_PROXIES' => '10.42.0.1'];
$envAvant = [];
foreach ($envDomaine as $key => $value) {
    $envAvant[$key] = [getenv($key), $_ENV[$key] ?? null, $_SERVER[$key] ?? null];
    putenv($key.'='.$value); $_ENV[$key] = $value; $_SERVER[$key] = $value;
}
try {
    $verifier($consoleKernel->call('config:cache') === 0, 'compilation configuration de domaines et proxys');
    $verifier($consoleKernel->call('route:cache') === 0, 'compilation du cache de routes');
    $cachedApp = require $application.'/bootstrap/app.php';
    $kernel = $cachedApp->make(\Illuminate\Contracts\Http\Kernel::class);
    $kernel->bootstrap();
    $cachedApp['config']->set('session.domain', null);
    $verifier($cachedApp->routesAreCached() && $httpDomaine($public)->getStatusCode() === 200
        && $httpDomaine($public, '/identites')->getStatusCode() === 404, 'accueil et frontière effectifs avec cache de routes');
    $verifier($httpDomaine($console, '/espace', ['REMOTE_ADDR' => '10.42.0.1', 'HTTP_X_FORWARDED_HOST' => $public])->headers->get('Location') === 'https://'.$public.'/reseau/connexion', 'proxy et URL effectifs avec configuration compilée');
} finally {
    @unlink($app->getCachedRoutesPath());
    @unlink($app->getCachedConfigPath());
    foreach ($envAvant as $key => [$old, $oldEnv, $oldServer]) {
        putenv($old === false ? $key : $key.'='.$old);
        if ($oldEnv === null) { unset($_ENV[$key]); } else { $_ENV[$key] = $oldEnv; }
        if ($oldServer === null) { unset($_SERVER[$key]); } else { $_SERVER[$key] = $oldServer; }
    }
    \Illuminate\Container\Container::setInstance($app);
    \Illuminate\Support\Facades\Facade::setFacadeApplication($app);
    $kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
    $app['config']->set('gamad_domains.enabled', false);
    \Illuminate\Http\Middleware\TrustProxies::flushState();
}
