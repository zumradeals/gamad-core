<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Preuves\AccesPreuves;
use Gamad\RegistrePreuves\PolitiquePreuves;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Preuves d'intégrité — lecture, vérification et cycle (CAP-CORE-015,
 * partie 4 §2, §4-5).
 *
 * L'API n'expose jamais une signature de contenu libre ni le choix d'une
 * clé par l'appelant (fiche partie 4 §1) : la seule émission possible ici
 * est l'empreinte d'un contenu JSON borné fourni inline, jamais signée
 * automatiquement. Manifeste, checkpoint et attestation restent des
 * raccordements gouvernés côté CLI d'exploitation, qui seule connaît les
 * chemins et magasins réels autorisés.
 */
final class PreuveController
{
    public function index(Request $request, AccesPreuves $acces): JsonResponse
    {
        $filtres = $request->only(['type_preuve', 'realm_reference']);
        $execution = $acces->lister($filtres, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function store(Request $request, AccesPreuves $acces): JsonResponse
    {
        $donnees = $request->validate([
            'sujet_type' => ['required', 'string', 'max:64'],
            'sujet_reference' => ['required', 'string', 'max:128'],
            'producteur_capacite_reference' => ['required', 'string', 'max:64'],
            'realm_reference' => ['required', 'string', 'max:64'],
            'finalite_reference' => ['required', 'string', 'max:255'],
            'source_reference' => ['required', 'string', 'max:255'],
            'classification' => ['required', 'string', 'in:' . implode(',', PolitiquePreuves::CLASSIFICATIONS)],
            'algorithme' => ['nullable', 'string', 'in:' . implode(',', PolitiquePreuves::ALGORITHMES_EMPREINTE_AUTORISES)],
            'contenu_json' => ['required', 'string', 'max:' . PolitiquePreuves::CONTENU_INLINE_MAX_OCTETS],
            'idempotency_key' => ['nullable', 'string', 'max:128'],
        ]);
        $execution = $acces->emettreEmpreinte($donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function show(Request $request, AccesPreuves $acces, string $reference): JsonResponse
    {
        $execution = $acces->resoudre($reference, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function etat(Request $request, AccesPreuves $acces, string $reference): JsonResponse
    {
        $execution = $acces->etat($reference, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function empreintes(Request $request, AccesPreuves $acces, string $reference): JsonResponse
    {
        $execution = $acces->empreintes($reference, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function signatures(Request $request, AccesPreuves $acces, string $reference): JsonResponse
    {
        $execution = $acces->signatures($reference, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function manifeste(Request $request, AccesPreuves $acces, string $reference): JsonResponse
    {
        $execution = $acces->manifeste($reference, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function attestation(Request $request, AccesPreuves $acces, string $reference): JsonResponse
    {
        $execution = $acces->attestation($reference, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function checkpoint(Request $request, AccesPreuves $acces, string $reference): JsonResponse
    {
        $execution = $acces->checkpoint($reference, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function verifications(Request $request, AccesPreuves $acces, string $reference): JsonResponse
    {
        $execution = $acces->verifications($reference, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function liens(Request $request, AccesPreuves $acces, string $reference): JsonResponse
    {
        $execution = $acces->liens($reference, (string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function diagnostic(Request $request, AccesPreuves $acces): JsonResponse
    {
        $execution = $acces->diagnostiquer((string) $request->attributes->get('gamad_entite'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function verification(Request $request, AccesPreuves $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate([
            'empreinte_presentee' => ['nullable', 'string', 'max:512'],
        ]);
        $execution = $acces->verifier($reference, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function verificationPaquet(Request $request, AccesPreuves $acces): JsonResponse
    {
        $donnees = $request->validate([
            'format' => ['required', 'string'],
            'version' => ['required', 'integer'],
            'profil' => ['required', 'string'],
            'preuve_reference' => ['required', 'string', 'max:64'],
            'type_preuve' => ['nullable'],
            'empreintes' => ['nullable', 'array'],
            'signatures' => ['nullable', 'array'],
            'etat' => ['nullable', 'string'],
        ]);
        $execution = $acces->verifierPaquet($donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function suspension(Request $request, AccesPreuves $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate([
            'motif_code' => ['required', 'string', 'max:64'],
            'motif_detail' => ['nullable', 'string', 'max:1000'],
        ]);
        $execution = $acces->suspendre($reference, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function revocation(Request $request, AccesPreuves $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate([
            'motif_code' => ['required', 'string', 'max:64'],
            'motif_detail' => ['nullable', 'string', 'max:1000'],
        ]);
        $execution = $acces->revoquer($reference, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function compromission(Request $request, AccesPreuves $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate([
            'motif_code' => ['required', 'string', 'max:64'],
            'motif_detail' => ['nullable', 'string', 'max:1000'],
            'date_effet' => ['nullable', 'date'],
        ]);
        $execution = $acces->declarerCompromission($reference, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }

    public function export(Request $request, AccesPreuves $acces, string $reference): JsonResponse
    {
        $donnees = $request->validate([
            'profil' => ['required', 'string', 'in:' . implode(',', PolitiquePreuves::PROFILS_EXPORT)],
        ]);
        $execution = $acces->exporter($reference, $donnees, (string) $request->attributes->get('gamad_entite'), $request->attributes->get('gamad_correlation'));

        return response()->json($execution['corps'], $execution['statut']);
    }
}
