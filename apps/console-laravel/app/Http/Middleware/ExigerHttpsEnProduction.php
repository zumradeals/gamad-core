<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuse toute API en clair en production.
 *
 * Le proxy frontal doit figurer dans TRUSTED_PROXIES afin que Laravel puisse
 * interpréter X-Forwarded-Proto sans faire confiance à un client arbitraire.
 */
final class ExigerHttpsEnProduction
{
    public function handle(Request $request, Closure $suivant): Response
    {
        if (app()->environment('production') && !$request->isSecure()) {
            return new JsonResponse(
                [
                    'erreur' => 'HTTPS_REQUIS',
                    'message' => 'L’API de production refuse les connexions non chiffrées.',
                ],
                426,
                ['Upgrade' => 'TLS/1.2, HTTP/1.1'],
            );
        }

        return $suivant($request);
    }
}
