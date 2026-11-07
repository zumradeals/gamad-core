<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

final class AccueilApplicationController
{
    public function __invoke(Request $request)
    {
        if (config('gamad_domains.enabled') && $request->attributes->get('gamad_domaine_institutionnel')) {
            return redirect('/console');
        }
        $application = $request->attributes->get('gamad_application');
        // Adaptateur livré : aucun template ou contrôleur choisi par le navigateur.
        if (config('gamad_domains.enabled') && ($application['route_accueil'] ?? null) !== 'reseau.accueil') {
            abort(404);
        }
        return view('reseau.accueil');
    }
}
