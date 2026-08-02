<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Sources\AccesSources;
use Gamad\RegistreAutorisation\Ctr03;
use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreIdentites\PolitiqueInscription;
use Gamad\RegistreNormes\BaselineOperationnelle;
use Gamad\RegistreNormes\Db;
use Gamad\RegistreProduits\Magasin as ProduitsMagasin;
use Gamad\RegistreSources\Magasin as SourcesMagasin;
use Gamad\RegistreSources\PolitiqueSources;
use Gamad\RegistreSources\RegistreSources;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Écran d'administration du registre des sources (CAP-CORE-006).
 *
 * Vit sous `/registre-sources` — et non `/sources` — parce que
 * `GET /sources/{reference}` reste la route web historique de `CTR-04`
 * (réponse JSON, préservée pour compatibilité). Les lectures interrogent
 * directement le registre ; toute écriture passe par `AccesSources`, le même
 * cas d'usage gouverné que l'API v1 : la console n'ouvre aucun chemin
 * parallèle et n'écrit jamais en direct dans le magasin des sources.
 */
final class SourceConsoleController
{
    public function index(Request $request): View
    {
        $acteur = (string) $request->attributes->get('gamad_entite');
        $autorite = $acteur === PolitiqueInscription::AUTORITE_INSCRIPTION;
        $etat = trim((string) $request->query('etat', ''));
        $type = trim((string) $request->query('type', ''));
        $recherche = mb_strtolower(trim((string) $request->query('q', '')), 'UTF-8');

        $tous = $this->registre()->listerSources();
        $visibles = array_values(array_filter(
            $tous,
            static fn (array $s): bool => $s['etat'] === 'ACTIVE'
                || $autorite
                || $s['proprietaire_reference'] === $acteur,
        ));
        $sources = array_values(array_filter(
            $visibles,
            static function (array $s) use ($etat, $type, $recherche): bool {
                if ($etat !== '' && $s['etat'] !== $etat) {
                    return false;
                }
                if ($type !== '' && $s['type_source'] !== $type) {
                    return false;
                }
                if ($recherche === '') {
                    return true;
                }
                $texte = mb_strtolower($s['nom_affichage'].' '.$s['reference'], 'UTF-8');

                return str_contains($texte, $recherche);
            },
        ));

        return view('sources.index', [
            'sources' => $sources,
            'total' => count($visibles),
            'autorite' => $autorite,
            'filtres' => ['q' => $request->query('q'), 'etat' => $etat, 'type' => $type],
            'etats' => PolitiqueSources::ETATS_CYCLE,
            'types' => PolitiqueSources::TYPES_SOURCE,
        ]);
    }

    public function create(Request $request): View
    {
        $acteur = (string) $request->attributes->get('gamad_entite');
        $decision = (new Ctr03(Db::connect()))->autoriser($acteur, PolitiqueSources::ACTION_INSCRIRE, null);

        return view('sources.create', [
            'acteur' => $acteur,
            'decision' => $decision,
            'inscriptionDisponible' => $decision['decision'] === 'PERMIS',
            'types' => PolitiqueSources::TYPES_SOURCE,
        ]);
    }

    public function store(Request $request, AccesSources $acces): RedirectResponse
    {
        $donnees = $request->validate([
            'reference' => ['required', 'string', 'max:64'],
            'nom_canonique' => ['required', 'string', 'max:255'],
            'nom_affichage' => ['required', 'string', 'max:255'],
            'type_source' => ['required', 'string', 'in:'.implode(',', PolitiqueSources::TYPES_SOURCE)],
            'proprietaire_reference' => ['required', 'string', 'max:64'],
            'categorie' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:2000'],
            'produit_producteur_reference' => ['nullable', 'string', 'max:64'],
            'reserve' => ['nullable', 'string', 'max:2000'],
        ]);

        $execution = $acces->inscrire($donnees, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 201) {
            return back()->withInput()->withErrors(['inscription' => $this->motif($execution['corps'])]);
        }

        $reference = (string) $execution['corps']['resultat']['reference'];

        return redirect()
            ->route('console.sources.show', $reference)
            ->with('succes', 'Source inscrite en PREPARATION.')
            ->with('preuve', $execution['corps']['preuve']);
    }

    public function show(Request $request, string $reference): View
    {
        $registre = $this->registre();
        $source = $registre->resoudreSource($reference);
        abort_if($source === null, 404);

        $acteur = (string) $request->attributes->get('gamad_entite');
        $autorite = $acteur === PolitiqueInscription::AUTORITE_INSCRIPTION;
        abort_unless(
            $source['etat'] === 'ACTIVE' || $autorite || $source['proprietaire_reference'] === $acteur,
            404,
        );

        return view('sources.show', [
            'source' => $source,
            'historique' => $registre->resoudreHistorique($reference),
            'revisions' => $registre->resoudreRevisions($reference),
            'verifications' => $registre->resoudreVerifications($reference),
            'finalites' => $registre->resoudreFinalites($reference),
            'lignee' => $registre->resoudreLignee($reference),
            'autorite' => $autorite,
            'types' => PolitiqueSources::TYPES_SOURCE,
            'niveaux' => PolitiqueSources::NIVEAUX_VERIFICATION,
            'resultats' => PolitiqueSources::RESULTATS_VERIFICATION,
            'typesLignee' => PolitiqueSources::TYPES_LIGNEE,
        ]);
    }

    public function modifier(Request $request, AccesSources $acces, string $reference): RedirectResponse
    {
        $donnees = $request->validate([
            'nom_affichage' => ['nullable', 'string', 'max:255'],
            'categorie' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:2000'],
            'proprietaire_reference' => ['nullable', 'string', 'max:64'],
            'produit_producteur_reference' => ['nullable', 'string', 'max:64'],
            'reserve' => ['nullable', 'string', 'max:2000'],
        ]);
        $donnees = array_filter($donnees, static fn (mixed $v): bool => $v !== null);

        $execution = $acces->modifier($reference, $donnees, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 200) {
            return back()->withErrors(['source' => $this->motif($execution['corps'])]);
        }

        return redirect()
            ->route('console.sources.show', $reference)
            ->with('succes', 'Métadonnées mises à jour (nouvelle révision).')
            ->with('preuve', $execution['corps']['preuve']);
    }

    public function activer(Request $request, AccesSources $acces, string $reference): RedirectResponse
    {
        return $this->transition($request, $acces, $reference, 'activer', 'Source activée.');
    }

    public function suspendre(Request $request, AccesSources $acces, string $reference): RedirectResponse
    {
        return $this->transition($request, $acces, $reference, 'suspendre', 'Source suspendue. Tout nouvel usage est immédiatement fermé.');
    }

    public function retirer(Request $request, AccesSources $acces, string $reference): RedirectResponse
    {
        return $this->transition($request, $acces, $reference, 'retirer', 'Source retirée. Son historique reste consultable.');
    }

    private function transition(
        Request $request,
        AccesSources $acces,
        string $reference,
        string $methode,
        string $messageSucces,
    ): RedirectResponse {
        $donnees = $request->validate(['motif' => ['nullable', 'string', 'max:500']]);
        $execution = $acces->{$methode}($reference, $donnees, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 200) {
            return back()->withErrors(['source' => $this->motif($execution['corps'])]);
        }

        return redirect()
            ->route('console.sources.show', $reference)
            ->with('succes', $messageSucces)
            ->with('preuve', $execution['corps']['preuve']);
    }

    public function declarerFinalite(Request $request, AccesSources $acces, string $reference): RedirectResponse
    {
        $donnees = $request->validate([
            'finalite_reference' => ['required', 'string', 'max:128'],
            'produit_consommateur_reference' => ['nullable', 'string', 'max:64'],
            'date_debut' => ['nullable', 'date_format:Y-m-d'],
            'date_fin' => ['nullable', 'date_format:Y-m-d'],
            'restriction' => ['nullable', 'string', 'max:1000'],
        ]);

        $execution = $acces->declarerFinalite($reference, $donnees, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 201) {
            return back()->withInput()->withErrors(['finalite' => $this->motif($execution['corps'])]);
        }

        return redirect()
            ->route('console.sources.show', $reference)
            ->with('succes', 'Finalité déclarée.')
            ->with('preuve', $execution['corps']['preuve']);
    }

    public function fermerFinalite(Request $request, AccesSources $acces, string $reference, int $id): RedirectResponse
    {
        $execution = $acces->fermerFinalite($reference, $id, [], (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 200) {
            return back()->withErrors(['finalite' => $this->motif($execution['corps'])]);
        }

        return redirect()
            ->route('console.sources.show', $reference)
            ->with('succes', 'Finalité fermée.')
            ->with('preuve', $execution['corps']['preuve']);
    }

    public function enregistrerVerification(Request $request, AccesSources $acces, string $reference): RedirectResponse
    {
        $donnees = $request->validate([
            'niveau' => ['required', 'string', 'in:'.implode(',', PolitiqueSources::NIVEAUX_VERIFICATION)],
            'resultat' => ['required', 'string', 'in:'.implode(',', PolitiqueSources::RESULTATS_VERIFICATION)],
            'verifie_par_reference' => ['required', 'string', 'max:64'],
            'verifie_le' => ['nullable', 'date_format:Y-m-d'],
            'expire_le' => ['nullable', 'date_format:Y-m-d'],
            'motif' => ['nullable', 'string', 'max:1000'],
        ]);

        $execution = $acces->enregistrerVerification($reference, $donnees, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 201) {
            return back()->withInput()->withErrors(['verification' => $this->motif($execution['corps'])]);
        }

        return redirect()
            ->route('console.sources.show', $reference)
            ->with('succes', 'Vérification enregistrée.')
            ->with('preuve', $execution['corps']['preuve']);
    }

    public function declarerLignee(Request $request, AccesSources $acces, string $reference): RedirectResponse
    {
        $donnees = $request->validate([
            'source_parente_reference' => ['required', 'string', 'max:64'],
            'type_relation' => ['required', 'string', 'in:'.implode(',', PolitiqueSources::TYPES_LIGNEE)],
        ]);

        $execution = $acces->declarerLignee($reference, $donnees, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 201) {
            return back()->withInput()->withErrors(['lignee' => $this->motif($execution['corps'])]);
        }

        return redirect()
            ->route('console.sources.show', $reference)
            ->with('succes', 'Relation de lignée déclarée.')
            ->with('preuve', $execution['corps']['preuve']);
    }

    private function registre(): RegistreSources
    {
        $index = Db::connect();
        try {
            $vide = ((int) $index->query('SELECT count(*) FROM entite')->fetchColumn()) === 0;
        } catch (\Throwable) {
            $vide = true;
        }
        if ($vide) {
            BaselineOperationnelle::standard()->reconstruire($index);
        }
        $registreIdentites = IdentiteMagasin::connecter();

        return new RegistreSources(
            $index,
            $registreIdentites,
            SourcesMagasin::connecter(),
            ProduitsMagasin::connecter(),
            new Ctr01($index, $registreIdentites),
        );
    }

    /** @param array<string,mixed> $corps */
    private function motif(array $corps): string
    {
        return (string) ($corps['resultat']['detail']
            ?? $corps['message']
            ?? $corps['decision']['motif']
            ?? 'Le Core a refusé cette opération.');
    }
}
