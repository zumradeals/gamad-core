<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Comptes\AnnuaireGamad;
use App\Application\Comptes\ExperiencesProfilGamad;
use App\Application\Comptes\LireCompteGamad;
use App\Application\Comptes\ModifierProfilGamad;
use App\Application\Reseau\ReferentielBesoinsGamad;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use App\Application\Reseau\ContenusGamad;
use App\Application\Reseau\RelationsGamad;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

final class ProfilCompteGamadController
{
    public function espace(Request $request, LireCompteGamad $lecture, AnnuaireGamad $annuaire, ExperiencesProfilGamad $experiences, ContenusGamad $contenus, RelationsGamad $relations): Response
    {
        $compte = $lecture->executer((string) $request->attributes->get('gamad_entite'));
        abort_if($compte === null, 403);

        $fiche = $annuaire->fichePersonnelle($compte['identite_reference']);
        $realisations = $experiences->realisations($compte['identite_reference']);
        $capacites = array_values(array_filter(explode("\n", $fiche['competences_apportees'] ?? '')));
        $criteres = [
            'Présentation' => filled($compte['bio']),
            'Localité' => filled($fiche['territoire'] ?? null),
            'Capacités apportées' => $capacites !== [],
            'Domaines d’action' => ($fiche['domaines'] ?? []) !== [],
            'Expériences' => ($fiche['experiences'] ?? []) !== [],
        ];

        return response()->view('reseau.mon-espace', [
            'compte' => $compte,
            'fiche' => $annuaire->fichePersonnelle($compte['identite_reference']),
            'territoires' => ReferentielBesoinsGamad::libelles('territoires'),
            'realisations' => $realisations,
            'domaines' => ReferentielBesoinsGamad::libelles('domaines'),
            'bioMax' => ModifierProfilGamad::BIO_MAX,
            'typesExperience' => ExperiencesProfilGamad::TYPES,
            'typesPublication' => ContenusGamad::TYPES,
            'capacites' => $capacites,
            'criteres' => $criteres,
            'completude' => (int) (count(array_filter($criteres)) * 100 / count($criteres)),
            'activite' => $contenus->personnelles($compte['identite_reference']),
            'relations' => $relations->lister($compte['identite_reference']),
        ])->header('Cache-Control', 'private, no-store');
    }

    private function reponse(Request $request): RedirectResponse|JsonResponse
    {
        if ($request->boolean('_espace') && $request->expectsJson()) {
            return response()->json(['enregistre' => true])->header('Cache-Control', 'private, no-store');
        }

        return redirect()->route($request->boolean('_espace') ? 'reseau.espace' : 'compte.profil')->with('profil_enregistre', true);
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

    public function modifier(Request $request, ModifierProfilGamad $modification): RedirectResponse|JsonResponse
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

        return $this->reponse($request);
    }

    public function enregistrerExperience(Request $request, ExperiencesProfilGamad $experiences, ?string $reference = null): RedirectResponse|JsonResponse
    {
        $donnees = $request->validate([
            'titre' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::in(array_keys(ExperiencesProfilGamad::TYPES))],
            'periode' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);
        $experiences->enregistrer((string) $request->attributes->get('gamad_entite'), $reference, $donnees);

        return $this->reponse($request);
    }

    public function retirerExperience(Request $request, ExperiencesProfilGamad $experiences, string $reference): RedirectResponse|JsonResponse
    {
        $experiences->retirer((string) $request->attributes->get('gamad_entite'), $reference);

        return $this->reponse($request);
    }

    public function lierPreuveExperience(Request $request, ExperiencesProfilGamad $experiences, string $reference): RedirectResponse|JsonResponse
    {
        $donnees = $request->validate(['publication' => ['required', 'regex:/^[a-f0-9]{24}$/D']]);
        $experiences->lierPreuve((string) $request->attributes->get('gamad_entite'), $reference, $donnees['publication']);

        return $this->reponse($request);
    }

    public function retirerPreuveExperience(Request $request, ExperiencesProfilGamad $experiences, string $reference): RedirectResponse|JsonResponse
    {
        $experiences->retirerPreuve((string) $request->attributes->get('gamad_entite'), $reference);

        return $this->reponse($request);
    }
}
