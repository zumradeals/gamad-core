<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;

/**
 * Throttle Core compatible avec les portails et satellites derrière proxy.
 *
 * Pour la porte POST /api/v1/sessions, l'IP n'est pas une identité : des
 * milliers d'utilisateurs peuvent partager la même sortie Vercel, Nginx ou
 * satellite. Le quota existant de la route (5/minute) est donc indexé par
 * l'empreinte de l'identifiant présenté, sans jamais écrire cet identifiant
 * ni le secret dans la clé de cache.
 *
 * Toutes les autres routes conservent exactement la signature Laravel par
 * défaut grâce à parent::resolveRequestSignature().
 */
final class GamadThrottleRequests extends ThrottleRequests
{
    protected function resolveRequestSignature($request)
    {
        if ($request instanceof Request
            && $request->is('api/v1/sessions')
            && $request->isMethod('POST')) {
            $presente = trim((string) ($request->input('identifiant') ?? $request->input('entite') ?? ''));
            $type = strtoupper(trim((string) ($request->input('type_identifiant') ?? 'ENTITE')));

            if ($presente !== '') {
                // La normalisation complète reste la responsabilité du registre
                // d'identité. Ici, on veut seulement une clé stable, non
                // réversible et insensible à la casse pour email/username/IDN.
                return 'gamad-session:' . hash(
                    'sha256',
                    $type . "\0" . mb_strtolower($presente, 'UTF-8'),
                );
            }

            return 'gamad-session:absent:' . hash('sha256', (string) $request->ip());
        }

        return parent::resolveRequestSignature($request);
    }
}
