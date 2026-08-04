<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Secrets\AccesSecrets;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Console d'administration du registre des secrets et clés (CAP-CORE-016).
 *
 * Toute lecture et toute écriture passent par `AccesSecrets`, le même cas
 * d'usage gouverné que l'API v1 — jamais d'écriture directe en base, jamais
 * de valeur affichée : les versions sont toujours rendues sans
 * `handle_fournisseur` (masqué par `AccesSecrets`).
 */
final class SecretConsoleController
{
    public function index(Request $request, AccesSecrets $acces): View
    {
        $acteur = (string) $request->attributes->get('gamad_entite');
        $liste = $acces->lister($request->only(['type_secret', 'environnement_reference']), $acteur);
        $diagnostic = $acces->diagnostiquer($acteur);

        return view('secrets-cles.tableau-de-bord', [
            'autorise' => $liste['statut'] === 200,
            'secrets' => $liste['statut'] === 200 ? $liste['corps']['secrets'] : [],
            'diagnostic' => $diagnostic['statut'] === 200 ? $diagnostic['corps'] : null,
            'motif' => $liste['statut'] === 200 ? null : $this->motif($liste['corps']),
        ]);
    }

    public function show(Request $request, AccesSecrets $acces, string $reference): View
    {
        $acteur = (string) $request->attributes->get('gamad_entite');
        $execution = $acces->resoudre($reference, $acteur);
        abort_if($execution['statut'] === 404, 404);
        abort_if($execution['statut'] === 403, 403, $this->motif($execution['corps']));

        $versions = $acces->listerVersions($reference, $acteur);
        $usages = $acces->listerUsages($reference, $acteur);
        $dependances = $acces->listerDependances($reference, $acteur);
        $rotations = $acces->listerRotations($reference, $acteur);

        return view('secrets-cles.secret', [
            'secret' => $execution['corps']['secret'],
            'versions' => $versions['corps']['versions'] ?? [],
            'usages' => $usages['corps']['usages'] ?? [],
            'dependances' => $dependances['corps']['dependances'] ?? [],
            'rotations' => $rotations['corps']['rotations'] ?? [],
        ]);
    }

    public function suspendreVersion(Request $request, AccesSecrets $acces, string $reference, int $id): RedirectResponse
    {
        $donnees = $request->validate(['motif' => ['required', 'string', 'max:500']]);
        $acces->suspendreVersion($reference, $id, $donnees, (string) $request->attributes->get('gamad_entite'), null);

        return redirect()->route('console.secrets-cles.show', $reference);
    }

    public function revoquerVersion(Request $request, AccesSecrets $acces, string $reference, int $id): RedirectResponse
    {
        $donnees = $request->validate(['motif' => ['required', 'string', 'max:500']]);
        $acces->revoquerVersion($reference, $id, $donnees, (string) $request->attributes->get('gamad_entite'), null);

        return redirect()->route('console.secrets-cles.show', $reference);
    }

    public function compromettreVersion(Request $request, AccesSecrets $acces, string $reference, int $id): RedirectResponse
    {
        $donnees = $request->validate([
            'niveau' => ['required', 'string'], 'source_reference' => ['required', 'string', 'max:64'],
            'motif' => ['required', 'string', 'max:1000'],
        ]);
        $donnees['secret_version_id'] = $id;
        $acces->declarerCompromission($donnees, (string) $request->attributes->get('gamad_entite'), null);

        return redirect()->route('console.secrets-cles.show', $reference);
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
