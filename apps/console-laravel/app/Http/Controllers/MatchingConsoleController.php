<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Matching\AccesMatching;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Console de lecture du Matching (CAP-CORE-021, doc de chantier 01 §5).
 *
 * Écran de lecture seule pour ce premier périmètre : toute lecture passe par
 * `AccesMatching`, le même cas d'usage gouverné que l'API v1. Aucune écriture
 * n'est exposée depuis cette console — soumettre une demande, exécuter,
 * construire un segment ou activer restent des opérations de produit
 * consommateur (API) ou d'exploitation (CLI), pas des boutons d'admin.
 * Aucun membre de segment n'est jamais affiché ici (doc 01 §4, doc 04 §6).
 */
final class MatchingConsoleController
{
    public function index(Request $request, AccesMatching $acces): View
    {
        $acteur = (string) $request->attributes->get('gamad_entite');
        $contextes = $acces->listerContextes($acteur);
        $demandes = $acces->listerDemandes($request->only(['contexte_reference', 'consommateur_produit', 'etat']), $acteur);

        return view('matching.tableau-de-bord', [
            'autorise' => $contextes['statut'] === 200,
            'contextes' => $contextes['statut'] === 200 ? $contextes['corps']['contextes'] : [],
            'demandes' => $demandes['statut'] === 200 ? $demandes['corps']['demandes'] : [],
            'motif' => $contextes['statut'] === 200 ? null : $this->motif($contextes['corps']),
        ]);
    }

    public function showDemande(Request $request, AccesMatching $acces, string $reference): View
    {
        $acteur = (string) $request->attributes->get('gamad_entite');
        $execution = $acces->resoudreDemande($reference, $acteur);
        abort_if($execution['statut'] === 404, 404);
        abort_if($execution['statut'] === 403, 403, $this->motif($execution['corps']));

        $demande = $execution['corps']['demande'];
        $historique = $acces->historiqueDemande($reference, $acteur);
        $registre = new \Gamad\MoteurMatching\RegistreMatching(\Gamad\MoteurMatching\Magasin::connecter());
        $executions = $registre->listerExecutions($reference);
        $derniereExecution = $executions[0] ?? null;
        $resultats = $derniereExecution !== null ? $registre->listerResultats((string) $derniereExecution['reference']) : [];
        $explications = [];
        foreach ($resultats as $resultat) {
            $projection = $acces->expliquerResultat((string) $resultat['reference'], $acteur);
            $explications[$resultat['reference']] = $projection['statut'] === 200 ? $projection['corps']['explication'] : null;
        }
        $segments = $registre->segmentsDeDemande($reference);

        return view('matching.demande', [
            'demande' => $demande,
            'historique' => $historique['statut'] === 200 ? $historique['corps']['historique'] : [],
            'executions' => $executions,
            'derniereExecution' => $derniereExecution,
            'resultats' => $resultats,
            'explications' => $explications,
            'segments' => $segments,
        ]);
    }

    public function showContexte(Request $request, AccesMatching $acces, string $reference): View
    {
        $acteur = (string) $request->attributes->get('gamad_entite');
        $execution = $acces->resoudreContexte($reference, $acteur);
        abort_if($execution['statut'] === 404, 404);
        abort_if($execution['statut'] === 403, 403, $this->motif($execution['corps']));

        $registre = new \Gamad\MoteurMatching\RegistreMatching(\Gamad\MoteurMatching\Magasin::connecter());

        return view('matching.contexte', [
            'contexte' => $execution['corps']['contexte'],
            'profilActif' => $registre->resoudreProfilActif($reference),
        ]);
    }

    public function showSegment(Request $request, AccesMatching $acces, string $reference): View
    {
        $acteur = (string) $request->attributes->get('gamad_entite');
        $execution = $acces->resoudreSegment($reference, $acteur);
        abort_if($execution['statut'] === 404, 404);
        abort_if($execution['statut'] === 403, 403, $this->motif($execution['corps']));

        return view('matching.segment', ['segment' => $execution['corps']['segment']]);
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
