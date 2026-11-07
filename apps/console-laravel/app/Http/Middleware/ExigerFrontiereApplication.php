<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Routes web : refus par défaut sur les domaines publics, avant création de session. */
final class ExigerFrontiereApplication
{
    public function handle(Request $request, Closure $suivant): Response
    {
        if (!config('gamad_domains.enabled') || $request->attributes->get('gamad_domaine_institutionnel')) {
            return $suivant($request);
        }
        $application = $request->attributes->get('gamad_application');
        $scope = $request->route()?->getAction('gamad_application');
        if (!is_array($application) || $scope !== $application['identifiant']) {
            abort(404);
        }
        return $suivant($request);
    }
}
