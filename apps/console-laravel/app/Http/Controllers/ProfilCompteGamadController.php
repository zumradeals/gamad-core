<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Comptes\AnnuaireGamad;
use App\Application\Comptes\LireCompteGamad;
use App\Application\Comptes\ModifierProfilGamad;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class ProfilCompteGamadController
{
    public function montrer(Request $request, LireCompteGamad $lecture, AnnuaireGamad $annuaire): Response
    {
        $compte = $lecture->executer((string) $request->attributes->get('gamad_entite'));
        abort_if($compte === null, 403);

        return response()->view('acces.profil', [
            'compte' => $compte,
            'bioMax' => ModifierProfilGamad::BIO_MAX,
            'fiche' => $annuaire->fichePersonnelle($compte['identite_reference']),
        ])
            ->header('Cache-Control', 'no-store');
    }

    public function modifier(Request $request, ModifierProfilGamad $modification): RedirectResponse
    {
        $donnees = $request->validate([
            'visibilite' => ['sometimes', 'required', 'in:prive,public,members'],
            'apportees' => ['nullable', 'string', 'max:800'],
            'recherchees' => ['nullable', 'string', 'max:800'],
            'bio' => ['present', 'nullable', 'string', 'max:'.ModifierProfilGamad::BIO_MAX],
        ]);

        abort_unless($modification->executer(
            (string) $request->attributes->get('gamad_entite'),
            $donnees['bio'],
            isset($donnees['visibilite']) ? $donnees : null,
        ), 403);

        return redirect()->route('compte.profil')->with('profil_enregistre', true);
    }
}
