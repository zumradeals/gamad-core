<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Gamad\RegistreProduits\ContratApplicationInterne;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/** Avant TrustProxies : ne jamais accepter une autorité transmise sans confiance explicite. */
final class ValiderHoteApplication
{
    public static function hote(string $valeur): string
    {
        if (!preg_match('/\A([a-zA-Z0-9.-]+)(?::([0-9]{1,5}))?\z/D', $valeur, $m)
            || (isset($m[2]) && ((int) $m[2] < 1 || (int) $m[2] > 65535))) {
            throw new \InvalidArgumentException('Hôte invalide');
        }
        return ContratApplicationInterne::domaine($m[1]);
    }

    public function handle(Request $request, Closure $suivant): Response
    {
        if (!config('gamad_domains.enabled')) {
            return $suivant($request);
        }
        try {
            $institutionnel = ContratApplicationInterne::domaine((string) config('gamad_domains.institutional_host'));
            foreach (config('gamad_domains.trusted_proxies', []) as $proxy) {
                $parties = explode('/', $proxy);
                $ip = $parties[0];
                $bits = str_contains($ip, ':') ? 128 : 32;
                if (!filter_var($ip, FILTER_VALIDATE_IP) || count($parties) > 2
                    || (isset($parties[1]) && (!ctype_digit($parties[1]) || (int) $parties[1] < 1 || (int) $parties[1] > $bits))) {
                    throw new \InvalidArgumentException('Proxy non explicite');
                }
            }
            if (config('session.domain') !== null && config('session.domain') !== '') {
                throw new \InvalidArgumentException('Cookies host-only requis');
            }
        } catch (\InvalidArgumentException) {
            abort(503, 'Configuration multidomaine invalide');
        }
        $request->attributes->set('gamad_hote_institutionnel', $institutionnel);
        $transmis = false;
        foreach (array_keys($request->headers->all()) as $entete) {
            if (str_starts_with($entete, 'x-forwarded-') || $entete === 'forwarded') {
                $transmis = true;
            }
        }
        $fiable = IpUtils::checkIp((string) $request->server->get('REMOTE_ADDR', ''), config('gamad_domains.trusted_proxies', []));
        if ($transmis && !$fiable) {
            abort(400, 'En-têtes proxy non fiables');
        }
        // Un seul protocole de transmission, sans préfixe ou port réécrit implicitement.
        if ($request->headers->has('forwarded') || $request->headers->has('x-forwarded-prefix') || $request->headers->has('x-forwarded-port')) {
            abort(400, 'En-têtes proxy non pris en charge');
        }
        try {
            $hote = self::hote((string) $request->headers->get('host'));
            if ($request->headers->has('x-forwarded-host')) {
                $hote = self::hote((string) $request->headers->get('x-forwarded-host'));
            }
            if ($request->headers->has('x-forwarded-proto') && !in_array($request->headers->get('x-forwarded-proto'), ['http', 'https'], true)) {
                throw new \InvalidArgumentException('Protocole invalide');
            }
        } catch (\InvalidArgumentException) {
            abort(400, 'Hôte invalide');
        }
        $request->attributes->set('gamad_hote_valide', $hote);
        return $suivant($request);
    }
}
