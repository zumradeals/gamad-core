<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Gamad\JournalOperationnel\Journal;
use Gamad\JournalOperationnel\Magasin as JournalMagasin;
use Gamad\RegistreAcces\Ctr16;
use Gamad\RegistreAcces\Magasin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authentification bearer de l'API v1.
 *
 * La référence de session n'est jamais portée au journal. Une indisponibilité
 * du journal ferme l'accès : une opération sensible sans trace serait un mode
 * dégradé silencieux.
 */
final class AuthentifierApi
{
    public function handle(Request $request, Closure $suivant): Response
    {
        $session = $request->bearerToken();

        try {
            $verdict = is_string($session) && $session !== ''
                ? (new Ctr16(Magasin::connecter()))->verifierSession($session)
                : [
                    'valide' => false,
                    'entite' => null,
                    'assurance' => null,
                    'motif' => 'jeton absent',
                ];
        } catch (\Throwable) {
            return new JsonResponse(
                [
                    'erreur' => 'MAGASIN_ACCES_INDISPONIBLE',
                    'message' => 'L’accès est fermé car la session ne peut pas être vérifiée.',
                ],
                503,
            );
        }

        try {
            $preuve = (new Journal(JournalMagasin::connecter()))->enregistrer([
                'categorie' => 'SECURITE',
                'type' => 'AUTHENTIFICATION_API',
                'acteur' => $verdict['entite'] ?? null,
                'action' => $request->method() . ' /' . $request->path(),
                'decision' => ($verdict['valide'] ?? false) === true ? 'ACCEPTEE' : 'REFUSEE',
                'motif' => $verdict['motif'] ?? null,
                'correlation_id' => $request->header('X-Correlation-ID'),
                'donnees' => [
                    'assurance' => $verdict['assurance'] ?? null,
                    'adresse_ip_empreinte' => $this->empreinteIp($request),
                ],
            ]);
        } catch (\Throwable) {
            return new JsonResponse(
                [
                    'erreur' => 'JOURNAL_INDISPONIBLE',
                    'message' => 'L’accès est fermé car sa traçabilité ne peut pas être garantie.',
                ],
                503,
            );
        }

        if (($verdict['valide'] ?? false) !== true) {
            return new JsonResponse(
                [
                    'erreur' => 'AUTHENTIFICATION_REQUISE',
                    'message' => 'Session absente, invalide, expirée ou révoquée.',
                    'preuve' => $preuve,
                ],
                401,
                ['WWW-Authenticate' => 'Bearer'],
            );
        }

        $request->attributes->set('gamad_entite', $verdict['entite']);
        $request->attributes->set('gamad_assurance', $verdict['assurance']);
        $request->attributes->set('gamad_session', $session);
        $request->attributes->set('gamad_correlation', $preuve['correlation_id']);

        return $suivant($request);
    }

    private function empreinteIp(Request $request): ?string
    {
        $ip = $request->ip();

        return is_string($ip) && $ip !== ''
            ? hash_hmac('sha256', $ip, (string) config('app.key'))
            : null;
    }
}
