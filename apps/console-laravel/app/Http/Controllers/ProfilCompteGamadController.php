<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Comptes\AnnuaireGamad;
use App\Application\Comptes\LireCompteGamad;
use App\Application\Comptes\ModifierProfilGamad;
use App\Application\Reseau\ReferentielBesoinsGamad;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

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
            'territoires' => ReferentielBesoinsGamad::TERRITOIRES,
            'domaines' => ReferentielBesoinsGamad::DOMAINES,
        ])
            ->header('Cache-Control', 'no-store');
    }

    public function modifier(Request $request, ModifierProfilGamad $modification): RedirectResponse
    {
        $donnees = $request->validate([
            'visibilite' => ['sometimes', 'required', 'in:prive,public,members'],
            'apportees' => ['nullable', 'string', 'max:800'],
            'recherchees' => ['nullable', 'string', 'max:800'],
            'titre' => ['sometimes', 'nullable', 'string', 'max:100'],
            'introduction' => ['sometimes', 'nullable', 'string', 'max:1200'],
            'territoire' => ['sometimes', 'nullable', 'string', Rule::in(array_keys(ReferentielBesoinsGamad::TERRITOIRES))],
            'domaines' => ['sometimes', 'array', 'max:3'],
            'domaines.*' => ['required', 'string', 'distinct', Rule::in(array_keys(ReferentielBesoinsGamad::DOMAINES))],
            'domaines_present' => ['sometimes', 'accepted'],
            'bio' => ['present', 'nullable', 'string', 'max:'.ModifierProfilGamad::BIO_MAX],
        ]);

        if (isset($donnees['domaines_present'])) {
            $donnees['domaines'] ??= [];
        }

        abort_unless($modification->executer(
            (string) $request->attributes->get('gamad_entite'),
            $donnees['bio'],
            isset($donnees['visibilite']) ? $donnees : null,
        ), 403);

        return redirect()->route('compte.profil')->with('profil_enregistre', true);
    }
}
