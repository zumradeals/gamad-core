<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Secrets\AccesSecrets;
use Gamad\RegistreSecretsCles\PolitiqueSecretsCles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Gouvernance des fournisseurs de secrets (CAP-CORE-016, partie 4 §2-3). */
final class FournisseurSecretController
{
    public function index(Request $request, AccesSecrets $acces): JsonResponse
    {
        $execution = $acces->listerFournisseurs((string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function store(Request $request, AccesSecrets $acces): JsonResponse
    {
        $donnees = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'type_fournisseur' => ['required', 'string', 'in:' . implode(',', PolitiqueSecretsCles::TYPES_FOURNISSEUR)],
            'realm_reference' => ['nullable', 'string', 'max:64'],
            'environnement_reference' => ['required', 'string', 'in:' . implode(',', PolitiqueSecretsCles::ENVIRONNEMENTS)],
            'proprietaire_reference' => ['required', 'string', 'max:64'],
            'capacites' => ['nullable', 'array'],
            'configuration_reference' => ['nullable', 'string', 'max:255'],
        ]);
        $execution = $acces->inscrireFournisseur($donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }
}
