<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\EtatFondation;
use Gamad\JournalOperationnel\Magasin as JournalMagasin;
use Gamad\RegistreAutorisation\Ctr03;
use Gamad\RegistreAutorites\Ctr02;
use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreNormes\BaselineOperationnelle;
use Gamad\RegistreNormes\Ctr04;
use Gamad\RegistreNormes\Db;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Livraison HTTP du contrat CTR-04 — lecture seulement.
 *
 * Ce contrôleur ne contient aucune logique de résolution propre : il traduit
 * la requête en appel à Gamad\RegistreNormes\Ctr04 et la réponse de la méthode
 * en HTTP. Aucune méthode d'écriture n'est déclarée ici ni routée.
 */
final class Ctr04Controller
{
    private function ctr04(): Ctr04
    {
        return new Ctr04($this->index());
    }

    /**
     * L'index technique, initialisé depuis la baseline opérationnelle s'il est
     * encore vide. Aucune reconstruction n'est déclenchée sur un index peuplé :
     * la réindexation est une opération explicite (`registre:reindexer`).
     */
    private function index(): \PDO
    {
        $pdo = Db::connect();

        $vide = true;
        try {
            $vide = ((int) $pdo->query('SELECT count(*) FROM adoption')->fetchColumn()) === 0;
        } catch (\Throwable) {
            $vide = true;
        }
        if ($vide) {
            BaselineOperationnelle::standard()->reconstruire($pdo);
        }

        return $pdo;
    }

    public function tableauDeBord(Request $request, EtatFondation $etatFondation): View
    {
        $pdo = $this->index();
        $ctr04 = new Ctr04($pdo);
        $acteur = (string) $request->attributes->get('gamad_entite');

        $diagnostic = $ctr04->diagnostiquerIndex();

        $capacites = [];
        foreach (['CAP-CORE-001', 'CAP-CORE-003', 'CAP-CORE-004', 'CAP-CORE-005', 'CAP-CORE-007'] as $reference) {
            $etat = $ctr04->resoudreCapacite($reference, 'conception');
            $capacites[] = [
                'reference' => $reference,
                'valeur' => $etat['valeur'] ?? 'INDETERMINE',
                'date_effet' => $etat['date_effet'] ?? null,
            ];
        }

        $identites = (new Ctr01($pdo, IdentiteMagasin::connecter()))->resoudreInventaire();
        $parType = array_count_values(array_map(
            static fn (array $identite): string => (string) $identite['type'],
            $identites,
        ));
        $mandat = (new Ctr02($pdo))->resoudreMandat(null, $acteur, gmdate('Y-m-d'));
        $decisionInscription = (new Ctr03($pdo))->autoriser(
            $acteur,
            'inscrire une identité',
            'personne',
        );
        $fondation = $etatFondation->inspecter();

        try {
            $activite = JournalMagasin::ouvrir()->query(
                'SELECT reference,categorie,type_evenement,acteur,action,decision,cree_le
                 FROM evenement_operationnel ORDER BY sequence_id DESC LIMIT 8'
            )->fetchAll();
            $journalDisponible = true;
        } catch (\Throwable) {
            $activite = [];
            $journalDisponible = false;
        }

        $alertes = [];
        if (! $fondation['pret']) {
            $alertes[] = [
                'niveau' => 'danger',
                'titre' => 'Le Core nécessite une intervention',
                'detail' => 'Une dépendance ou une exigence de production ne répond pas.',
            ];
        }
        if (! $journalDisponible) {
            $alertes[] = [
                'niveau' => 'danger',
                'titre' => 'Le journal opérationnel est illisible',
                'detail' => 'Les traces d’audit ne peuvent pas être consultées depuis la console.',
            ];
        }
        if (! $diagnostic['coherent']) {
            $alertes[] = [
                'niveau' => 'danger',
                'titre' => 'L’index technique diverge de sa source d’initialisation',
                'detail' => implode(' · ', array_slice($diagnostic['divergences'], 0, 3)),
            ];
        }
        if ($decisionInscription['decision'] !== 'PERMIS') {
            $alertes[] = [
                'niveau' => 'attention',
                'titre' => 'L’inscription d’identité est fermée',
                'detail' => (string) $decisionInscription['motif'],
            ];
        }
        if (! is_array($mandat) || ! str_starts_with((string) ($mandat['etat'] ?? ''), 'ACTIF')) {
            $alertes[] = [
                'niveau' => 'attention',
                'titre' => 'Aucun mandat actif pour cette session',
                'detail' => 'Les actions réservées resteront refusées.',
            ];
        }

        return view('tableau-de-bord', [
            'acteur' => $acteur,
            'diagnostic' => $diagnostic,
            'capacites' => $capacites,
            'identites' => $identites,
            'parType' => $parType,
            'mandat' => $mandat,
            'decisionInscription' => $decisionInscription,
            'fondation' => $fondation,
            'activite' => $activite,
            'journalDisponible' => $journalDisponible,
            'alertes' => $alertes,
        ]);
    }

    public function resoudreNorme(Request $request, string $reference): JsonResponse
    {
        $resultat = $this->ctr04()->resoudreNorme(
            $reference,
            $request->query('version'),
            $request->query('date'),
        );

        if ($resultat === null) {
            return response()->json(['erreur' => 'norme introuvable', 'reference' => $reference], 404);
        }

        return response()->json($resultat);
    }

    public function resoudreSource(Request $request, string $reference): JsonResponse
    {
        $resultat = $this->ctr04()->resoudreSource($reference, $request->query('date'));

        if ($resultat === null) {
            return response()->json(['erreur' => 'source introuvable', 'reference' => $reference], 404);
        }

        return response()->json($resultat);
    }

    public function resoudreCapacite(Request $request, string $reference): JsonResponse
    {
        $resultat = $this->ctr04()->resoudreCapacite(
            $reference,
            $request->query('dimension', 'conception'),
            $request->query('date'),
        );

        if ($resultat === null) {
            return response()->json(['erreur' => 'capacité ou dimension introuvable', 'reference' => $reference], 404);
        }

        return response()->json($resultat);
    }

    /**
     * Diagnostic opérationnel de l'index : intégrité de la source
     * d'initialisation et concordance des volumes présents.
     */
    public function diagnostiquerIndex(): JsonResponse
    {
        $diagnostic = $this->ctr04()->diagnostiquerIndex();

        return response()->json($diagnostic, $diagnostic['coherent'] ? 200 : 409);
    }
}
