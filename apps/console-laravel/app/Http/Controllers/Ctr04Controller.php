<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\CheminsOperationnels;
use App\Support\EtatFondation;
use Gamad\JournalOperationnel\Magasin as JournalMagasin;
use Gamad\RegistreAutorisation\Ctr03;
use Gamad\RegistreAutorites\Ctr02;
use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreNormes\Ctr04;
use Gamad\RegistreNormes\Db;
use Gamad\RegistreNormes\Ingestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Livraison HTTP du contrat CTR-04 — lecture et attestation seulement (INV-4).
 *
 * Ce contrôleur ne contient aucune logique de résolution propre : il traduit
 * la requête en appel à Gamad\RegistreNormes\Ctr04 (cœur adopté, ADOPTION-0028
 * et ADOPTION-0029, non modifié par la présente couche) et la réponse de la
 * méthode en HTTP. Aucune méthode d'écriture n'est déclarée ici ni routée.
 */
final class Ctr04Controller
{
    private function ctr04(): Ctr04
    {
        $pdo = Db::connect();
        $corpus = dirname(base_path(), 2);

        $vide = true;
        try {
            $vide = ((int) $pdo->query('SELECT count(*) FROM adoption')->fetchColumn()) === 0;
        } catch (\Throwable) {
            $vide = true;
        }
        if ($vide) {
            (new Ingestion($pdo, $corpus))->executer();
        }

        return new Ctr04($pdo, $corpus);
    }

    public function tableauDeBord(Request $request, EtatFondation $etatFondation): View
    {
        $ctr04 = $this->ctr04();
        $pdo = Db::connect();
        $acteur = (string) $request->attributes->get('gamad_entite');
        $corpus = dirname(base_path(), 2);

        $adoptions = $pdo->query(
            'SELECT reference, autorite, date_adoption, signature_presente FROM adoption ORDER BY reference'
        )->fetchAll();
        $integrite = CheminsOperationnels::appliquer(
            $ctr04->verifierIntegrite(),
            $corpus,
        );
        $index = $ctr04->resoudreIndex();

        $indetermines = (int) $pdo->query(
            "SELECT count(*) FROM norme WHERE rang_code = 'INDETERMINE'"
        )->fetchColumn();

        $concordants = array_filter($integrite, fn ($l) => $l['concorde']);
        $divergents = array_filter($integrite, fn ($l) => ! $l['concorde']);

        $p3 = [];
        foreach ([['2026-07-26', 'EN CONCEPTION'], ['2026-07-27', 'CONÇUE'], ['2026-08-01', 'CONÇUE']] as [$d, $attendu]) {
            $r = $ctr04->resoudreCapacite('CAP-CORE-007', 'conception', $d);
            $p3[] = [
                'date' => $d,
                'attendu' => $attendu,
                'obtenu' => $r['valeur'] ?? '(aucun)',
                'ok' => ($r['valeur'] ?? null) === $attendu,
            ];
        }
        $p3Ok = count(array_filter($p3, fn ($c) => $c['ok'])) === count($p3);

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
        } catch (\Throwable) {
            $activite = [];
        }

        $alertes = [];
        if (! $fondation['pret']) {
            $alertes[] = [
                'niveau' => 'danger',
                'titre' => 'Le Core nécessite une intervention',
                'detail' => 'Une dépendance ou une exigence de production ne répond pas.',
            ];
        }
        if (count($divergents) > 0 || count($index['divergences']) > 0) {
            $alertes[] = [
                'niveau' => 'danger',
                'titre' => 'Une divergence d’intégrité est détectée',
                'detail' => 'Consultez les contrôles avant toute nouvelle opération sensible.',
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
            'adoptions' => $adoptions,
            'concordants' => $concordants,
            'divergents' => $divergents,
            'index' => $index,
            'p3' => $p3,
            'p3Ok' => $p3Ok,
            'indetermines' => $indetermines,
            'identites' => $identites,
            'parType' => $parType,
            'mandat' => $mandat,
            'decisionInscription' => $decisionInscription,
            'fondation' => $fondation,
            'activite' => $activite,
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

    public function verifierIntegrite(?string $reference = null): JsonResponse
    {
        $lignes = CheminsOperationnels::appliquer(
            $this->ctr04()->verifierIntegrite($reference),
            dirname(base_path(), 2),
        );

        return response()->json($lignes);
    }

    public function resoudreIndex(): JsonResponse
    {
        return response()->json($this->ctr04()->resoudreIndex());
    }
}
