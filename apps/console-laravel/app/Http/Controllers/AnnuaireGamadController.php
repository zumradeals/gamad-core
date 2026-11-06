<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Comptes\AnnuaireGamad;
use App\Support\Normalizer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class AnnuaireGamadController
{
    public function index(Request $request, AnnuaireGamad $annuaire): Response
    {
        $saisie = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $recherche = Normalizer::key($saisie['q'] ?? '');
        $profils = $annuaire->rechercher($request->session()->get('gamad_session'));
        if ($recherche !== '') {
            $profils = array_filter($profils, static fn (array $profil): bool => str_contains(
                Normalizer::key(implode(' ', [$profil['nom'], ...$profil['apportees'], ...$profil['recherchees']])),
                $recherche,
            ));
        }

        return response()->view('reseau.personnes', ['profils' => $profils, 'recherche' => $saisie['q'] ?? ''])
            ->header('Cache-Control', 'private, no-store');
    }

    public function montrer(Request $request, AnnuaireGamad $annuaire, string $reference): Response
    {
        $profil = $annuaire->rechercher($request->session()->get('gamad_session'), $reference)[0] ?? null;
        abort_if($profil === null, 404);

        return response()->view('reseau.personnes', ['profils' => [$profil], 'fiche' => true])
            ->header('Cache-Control', 'private, no-store');
    }
}
