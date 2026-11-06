<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Comptes\AnnuaireGamad;
use App\Application\Comptes\ExperiencesProfilGamad;
use App\Application\Comptes\LireCompteGamad;
use App\Application\Comptes\ModifierProfilGamad;
use App\Application\Reseau\ReferentielBesoinsGamad;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

final class ProfilCompteGamadController
{
    public function espace(Request $request, LireCompteGamad $lecture, AnnuaireGamad $annuaire, ExperiencesProfilGamad $experiences): Response
    {
        $compte = $lecture->executer((string) $request->attributes->get('gamad_entite'));
        abort_if($compte === null, 403);

        return response()->view('reseau.mon-espace', [
            'compte' => $compte,
            'fiche' => $annuaire->fichePersonnelle($compte['identite_reference']),
            'territoires' => ReferentielBesoinsGamad::libelles('territoires'),
            'realisations' => array_slice($experiences->realisations($compte['identite_reference']), 0, 3),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function montrer(Request $request, LireCompteGamad $lecture, AnnuaireGamad $annuaire, ExperiencesProfilGamad $experiences): Response
    {
        $compte = $lecture->executer((string) $request->attributes->get('gamad_entite'));
        abort_if($compte === null, 403);

        return response()->view('acces.profil', [
            'compte' => $compte,
            'bioMax' => ModifierProfilGamad::BIO_MAX,
            'fiche' => $annuaire->fichePersonnelle($compte['identite_reference']),
            'territoires' => ReferentielBesoinsGamad::libelles('territoires'),
            'domaines' => ReferentielBesoinsGamad::libelles('domaines'),
            'typesExperience' => ExperiencesProfilGamad::TYPES,
            'realisations' => $experiences->realisations($compte['identite_reference']),
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

    public function enregistrerExperience(Request $request, ExperiencesProfilGamad $experiences, ?string $reference = null): RedirectResponse
    {
        $donnees = $request->validate([
            'titre' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::in(array_keys(ExperiencesProfilGamad::TYPES))],
            'periode' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);
        $experiences->enregistrer((string) $request->attributes->get('gamad_entite'), $reference, $donnees);

        return redirect()->route('compte.profil');
    }

    public function retirerExperience(Request $request, ExperiencesProfilGamad $experiences, string $reference): RedirectResponse
    {
        $experiences->retirer((string) $request->attributes->get('gamad_entite'), $reference);

        return redirect()->route('compte.profil');
    }

    public function lierPreuveExperience(Request $request, ExperiencesProfilGamad $experiences, string $reference): RedirectResponse
    {
        $donnees = $request->validate(['publication' => ['required', 'regex:/^[a-f0-9]{24}$/D']]);
        $experiences->lierPreuve((string) $request->attributes->get('gamad_entite'), $reference, $donnees['publication']);

        return redirect()->route('compte.profil');
    }

    public function retirerPreuveExperience(Request $request, ExperiencesProfilGamad $experiences, string $reference): RedirectResponse
    {
        $experiences->retirerPreuve((string) $request->attributes->get('gamad_entite'), $reference);

        return redirect()->route('compte.profil');
    }
}
