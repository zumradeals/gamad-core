<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Preuves\AccesPreuves;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Console d'administration du registre des preuves d'intégrité
 * (CAP-CORE-015, fiche partie 4 §9).
 *
 * Toute lecture et toute écriture passent par `AccesPreuves`, le même cas
 * d'usage gouverné que l'API v1 — jamais de signature de texte libre depuis
 * cet écran (fiche §9.4) : émission, checkpoint, manifeste et attestation
 * restent des commandes CLI d'exploitation.
 */
final class PreuveConsoleController
{
    public function index(Request $request, AccesPreuves $acces): View
    {
        $acteur = (string) $request->attributes->get('gamad_entite');
        $liste = $acces->lister($request->only(['type_preuve', 'realm_reference']), $acteur);
        $diagnostic = $acces->diagnostiquer($acteur);

        return view('preuves.tableau-de-bord', [
            'autorise' => $liste['statut'] === 200,
            'preuves' => $liste['statut'] === 200 ? $liste['corps']['preuves'] : [],
            'diagnostic' => $diagnostic['statut'] === 200 ? $diagnostic['corps']['registre'] : null,
            'motif' => $liste['statut'] === 200 ? null : $this->motif($liste['corps']),
        ]);
    }

    public function show(Request $request, AccesPreuves $acces, string $reference): View
    {
        $acteur = (string) $request->attributes->get('gamad_entite');
        $execution = $acces->resoudre($reference, $acteur);
        abort_if($execution['statut'] === 404, 404);
        abort_if($execution['statut'] === 403, 403, $this->motif($execution['corps']));

        $empreintes = $acces->empreintes($reference, $acteur);
        $signatures = $acces->signatures($reference, $acteur);
        $verifications = $acces->verifications($reference, $acteur);
        $liens = $acces->liens($reference, $acteur);
        $manifeste = $acces->manifeste($reference, $acteur);

        return view('preuves.preuve', [
            'preuve' => $execution['corps']['preuve'],
            'empreintes' => $empreintes['corps']['empreintes'] ?? [],
            'signatures' => $signatures['corps']['signatures'] ?? [],
            'verifications' => $verifications['corps']['verifications'] ?? [],
            'liens' => $liens['corps']['liens'] ?? [],
            'manifeste' => $manifeste['statut'] === 200 ? $manifeste['corps']['manifeste'] : null,
        ]);
    }

    public function verifier(Request $request, AccesPreuves $acces, string $reference): RedirectResponse
    {
        $donnees = $request->validate(['empreinte_presentee' => ['nullable', 'string', 'max:512']]);
        $acces->verifier($reference, $donnees, (string) $request->attributes->get('gamad_entite'), null);

        return redirect()->route('console.preuves.show', $reference);
    }

    public function suspendre(Request $request, AccesPreuves $acces, string $reference): RedirectResponse
    {
        $donnees = $request->validate([
            'motif_code' => ['required', 'string', 'max:64'],
            'motif_detail' => ['nullable', 'string', 'max:1000'],
        ]);
        $acces->suspendre($reference, $donnees, (string) $request->attributes->get('gamad_entite'), null);

        return redirect()->route('console.preuves.show', $reference);
    }

    public function revoquer(Request $request, AccesPreuves $acces, string $reference): RedirectResponse
    {
        $donnees = $request->validate([
            'motif_code' => ['required', 'string', 'max:64'],
            'motif_detail' => ['nullable', 'string', 'max:1000'],
        ]);
        $acces->revoquer($reference, $donnees, (string) $request->attributes->get('gamad_entite'), null);

        return redirect()->route('console.preuves.show', $reference);
    }

    public function compromettre(Request $request, AccesPreuves $acces, string $reference): RedirectResponse
    {
        $donnees = $request->validate([
            'motif_code' => ['required', 'string', 'max:64'],
            'motif_detail' => ['nullable', 'string', 'max:1000'],
        ]);
        $acces->declarerCompromission($reference, $donnees, (string) $request->attributes->get('gamad_entite'), null);

        return redirect()->route('console.preuves.show', $reference);
    }

    private function motif(array $corps): string
    {
        return (string) ($corps['resultat']['detail']
            ?? $corps['message']
            ?? $corps['decision']['motif']
            ?? $corps['erreur']
            ?? 'Le Core a refusé cette opération.');
    }
}
