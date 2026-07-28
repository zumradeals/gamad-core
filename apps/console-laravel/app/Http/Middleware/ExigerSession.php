<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Gamad\RegistreAcces\Ctr05;
use Gamad\RegistreAcces\Magasin;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige une session valide au sens de CTR-05.
 *
 * La validité n'est pas celle de la session applicative de Laravel mais celle
 * que `verifierSession` établit : expiration, révocation de la session, et
 * révocation de l'authentificateur qui l'a ouverte (M-21). Une session
 * applicative survivant à une révocation institutionnelle serait un défaut.
 */
final class ExigerSession
{
    public function handle(Request $request, Closure $suivant): Response
    {
        $reference = $request->session()->get('gamad_session');

        if (!is_string($reference)) {
            return redirect()->guest(route('acces.formulaire'));
        }

        $verdict = (new Ctr05(Magasin::connecter()))->verifierSession($reference);

        if (($verdict['valide'] ?? false) !== true) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('acces.formulaire')
                ->withErrors(['entite' => 'Session close : ' . ($verdict['motif'] ?? 'invalide') . '.']);
        }

        $request->attributes->set('gamad_entite', $verdict['entite']);
        $request->attributes->set('gamad_assurance', $verdict['assurance']);

        return $suivant($request);
    }
}
