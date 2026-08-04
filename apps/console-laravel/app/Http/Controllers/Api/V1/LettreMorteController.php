<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Evenements\AccesEvenements;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Écran / API des lettres mortes de CAP-CORE-014 (partie 4 §7).
 *
 * Aucun effacement : une lettre morte relancée redevient disponible côté
 * livraison, une lettre morte clôturée le reste jusqu'à relance éventuelle —
 * `AccesEvenements::cloturerLettreMorte()` documente comment cet état se
 * dérive sans muter la ligne append-only `lettre_morte_evenement`.
 */
final class LettreMorteController
{
    public function index(Request $request, AccesEvenements $acces): JsonResponse
    {
        $abonnement = $request->query('abonnement');
        $execution = $acces->listerLettresMortes($abonnement === null ? null : (string) $abonnement, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function show(Request $request, AccesEvenements $acces, string $reference): JsonResponse
    {
        $execution = $acces->resoudreLettreMorte($reference, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function relance(Request $request, AccesEvenements $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate(['motif' => ['required', 'string', 'max:500']]);
        $execution = $acces->relancerLettreMorte($reference, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function cloture(Request $request, AccesEvenements $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate(['motif' => ['required', 'string', 'max:500']]);
        $execution = $acces->cloturerLettreMorte($reference, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }
}
