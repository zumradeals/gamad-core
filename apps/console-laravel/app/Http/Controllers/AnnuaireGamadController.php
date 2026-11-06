<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Comptes\AnnuaireGamad;
use App\Application\Reseau\ReferentielBesoinsGamad;
use App\Support\Normalizer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

final class AnnuaireGamadController
{
    public function index(Request $request, AnnuaireGamad $annuaire): Response
    {
        $saisie = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'territoire' => ['nullable', 'string', Rule::in(array_keys(ReferentielBesoinsGamad::TERRITOIRES))],
            'domaine' => ['nullable', 'string', Rule::in(array_keys(ReferentielBesoinsGamad::DOMAINES))],
            'competence' => ['nullable', 'in:apportees,recherchees'],
        ]);
        $recherche = Normalizer::key($saisie['q'] ?? '');
        $profils = $annuaire->rechercher(
            $request->session()->get('gamad_session'),
            territoire: $saisie['territoire'] ?? null,
            domaine: $saisie['domaine'] ?? null,
        );
        $direction = $saisie['competence'] ?? null;
        $profils = array_filter($profils, static function (array $profil) use ($direction, $recherche): bool {
            $champs = $direction === null
                ? [$profil['nom'], $profil['titre'] ?? '', ...$profil['apportees'], ...$profil['recherchees']]
                : $profil[$direction];

            return $champs !== [] && str_contains(Normalizer::key(implode(' ', $champs)), $recherche);
        });

        return response()->view('reseau.personnes', [
            'profils' => $profils,
            'filtres' => $saisie,
            'territoires' => ReferentielBesoinsGamad::TERRITOIRES,
            'domaines' => ReferentielBesoinsGamad::DOMAINES,
        ])
            ->header('Cache-Control', 'private, no-store');
    }

    public function montrer(Request $request, AnnuaireGamad $annuaire, string $reference): Response
    {
        $profil = $annuaire->rechercher($request->session()->get('gamad_session'), $reference)[0] ?? null;
        abort_if($profil === null, 404);

        return response()->view('reseau.personnes', [
            'profils' => [$profil], 'fiche' => true,
            'territoires' => ReferentielBesoinsGamad::TERRITOIRES,
            'domaines' => ReferentielBesoinsGamad::DOMAINES,
        ])
            ->header('Cache-Control', 'private, no-store');
    }
}
