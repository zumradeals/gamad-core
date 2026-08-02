<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Produits\AccesProduits;
use Gamad\RegistreAutorisation\Ctr03;
use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreIdentites\PolitiqueInscription;
use Gamad\RegistreNormes\BaselineOperationnelle;
use Gamad\RegistreNormes\Db;
use Gamad\RegistrePolitiques\Magasin as PolitiquesMagasin;
use Gamad\RegistreProduits\Magasin as ProduitsMagasin;
use Gamad\RegistreProduits\PolitiqueProduits;
use Gamad\RegistreProduits\RegistreProduits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Écran d'administration du registre des produits (CAP-CORE-011).
 *
 * Les lectures interrogent directement le registre, comme l'écran Satellites
 * le fait pour CAP-CORE-022. Toute écriture passe par `AccesProduits`, le même
 * cas d'usage gouverné que l'API v1 : la console n'ouvre aucun chemin
 * parallèle et n'écrit jamais en direct dans le magasin des produits.
 */
final class ProduitConsoleController
{
    public function index(Request $request): View
    {
        $acteur = (string) $request->attributes->get('gamad_entite');
        $autorite = $acteur === PolitiqueInscription::AUTORITE_INSCRIPTION;
        $etat = trim((string) $request->query('etat', ''));
        $type = trim((string) $request->query('type', ''));
        $recherche = mb_strtolower(trim((string) $request->query('q', '')), 'UTF-8');

        $tous = $this->registre()->listerProduits();
        $visibles = array_values(array_filter(
            $tous,
            static fn (array $p): bool => $p['etat'] === 'ACTIF'
                || $autorite
                || $p['proprietaire_reference'] === $acteur,
        ));
        $produits = array_values(array_filter(
            $visibles,
            static function (array $p) use ($etat, $type, $recherche): bool {
                if ($etat !== '' && $p['etat'] !== $etat) {
                    return false;
                }
                if ($type !== '' && $p['type_produit'] !== $type) {
                    return false;
                }
                if ($recherche === '') {
                    return true;
                }
                $texte = mb_strtolower($p['nom_affichage'].' '.$p['reference'], 'UTF-8');

                return str_contains($texte, $recherche);
            },
        ));

        return view('produits.index', [
            'produits' => $produits,
            'total' => count($visibles),
            'autorite' => $autorite,
            'filtres' => ['q' => $request->query('q'), 'etat' => $etat, 'type' => $type],
            'etats' => PolitiqueProduits::ETATS_CYCLE,
            'types' => PolitiqueProduits::TYPES_PRODUIT,
        ]);
    }

    public function create(Request $request): View
    {
        $acteur = (string) $request->attributes->get('gamad_entite');
        $decision = (new Ctr03(PolitiquesMagasin::connecter()))->autoriser($acteur, PolitiqueProduits::ACTION_INSCRIRE, null);

        return view('produits.create', [
            'acteur' => $acteur,
            'decision' => $decision,
            'inscriptionDisponible' => $decision['decision'] === 'PERMIS',
            'types' => PolitiqueProduits::TYPES_PRODUIT,
        ]);
    }

    public function store(Request $request, AccesProduits $acces): RedirectResponse
    {
        $donnees = $request->validate([
            'reference' => ['required', 'string', 'max:64'],
            'identite_reference' => ['required', 'string', 'max:64'],
            'nom_canonique' => ['required', 'string', 'max:255'],
            'nom_affichage' => ['required', 'string', 'max:255'],
            'type_produit' => ['required', 'string', 'in:'.implode(',', PolitiqueProduits::TYPES_PRODUIT)],
            'proprietaire_reference' => ['required', 'string', 'max:64'],
        ]);

        $execution = $acces->inscrire($donnees, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 201) {
            return back()->withInput()->withErrors(['inscription' => $this->motif($execution['corps'])]);
        }

        $reference = (string) $execution['corps']['resultat']['reference'];

        return redirect()
            ->route('console.produits.show', $reference)
            ->with('succes', 'Produit inscrit en PREPARATION.')
            ->with('preuve', $execution['corps']['preuve']);
    }

    public function show(Request $request, string $reference): View
    {
        $registre = $this->registre();
        $produit = $registre->resoudreProduit($reference);
        abort_if($produit === null, 404);

        $acteur = (string) $request->attributes->get('gamad_entite');
        $autorite = $acteur === PolitiqueInscription::AUTORITE_INSCRIPTION;
        abort_unless(
            $produit['etat'] === 'ACTIF' || $autorite || $produit['proprietaire_reference'] === $acteur,
            404,
        );

        return view('produits.show', [
            'produit' => $produit,
            'historique' => $registre->resoudreHistorique($reference),
            'environnements' => $registre->resoudreEnvironnements($reference),
            'autorite' => $autorite,
            'types' => PolitiqueProduits::TYPES_PRODUIT,
            'environnementsListe' => PolitiqueProduits::ENVIRONNEMENTS,
        ]);
    }

    public function modifier(Request $request, AccesProduits $acces, string $reference): RedirectResponse
    {
        $donnees = $request->validate([
            'nom_canonique' => ['nullable', 'string', 'max:255'],
            'nom_affichage' => ['nullable', 'string', 'max:255'],
            'type_produit' => ['nullable', 'string', 'in:'.implode(',', PolitiqueProduits::TYPES_PRODUIT)],
            'proprietaire_reference' => ['nullable', 'string', 'max:64'],
            'federation_autorisee' => ['nullable', 'boolean'],
        ]);
        $donnees = array_filter($donnees, static fn (mixed $v): bool => $v !== null);
        if ($request->has('federation_autorisee')) {
            $donnees['federation_autorisee'] = $request->boolean('federation_autorisee');
        }

        $execution = $acces->modifier($reference, $donnees, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 200) {
            return back()->withErrors(['produit' => $this->motif($execution['corps'])]);
        }

        return redirect()
            ->route('console.produits.show', $reference)
            ->with('succes', 'Métadonnées mises à jour.')
            ->with('preuve', $execution['corps']['preuve']);
    }

    public function activer(Request $request, AccesProduits $acces, string $reference): RedirectResponse
    {
        return $this->transition($request, $acces, $reference, 'activer', 'Produit activé.');
    }

    public function suspendre(Request $request, AccesProduits $acces, string $reference): RedirectResponse
    {
        return $this->transition($request, $acces, $reference, 'suspendre', 'Produit suspendu. Ses jetons fédérés encore ouverts sont fermés.');
    }

    public function retirer(Request $request, AccesProduits $acces, string $reference): RedirectResponse
    {
        return $this->transition($request, $acces, $reference, 'retirer', 'Produit retiré. Son historique reste consultable.');
    }

    private function transition(
        Request $request,
        AccesProduits $acces,
        string $reference,
        string $methode,
        string $messageSucces,
    ): RedirectResponse {
        $donnees = $request->validate(['motif' => ['nullable', 'string', 'max:500']]);
        $execution = $acces->{$methode}($reference, $donnees, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 200) {
            return back()->withErrors(['produit' => $this->motif($execution['corps'])]);
        }

        return redirect()
            ->route('console.produits.show', $reference)
            ->with('succes', $messageSucces)
            ->with('preuve', $execution['corps']['preuve']);
    }

    public function declarerEnvironnement(Request $request, AccesProduits $acces, string $reference): RedirectResponse
    {
        $donnees = $request->validate([
            'environnement' => ['required', 'string', 'in:'.implode(',', PolitiqueProduits::ENVIRONNEMENTS)],
            'api_base_url' => ['required', 'string', 'max:2048'],
            'health_url' => ['nullable', 'string', 'max:2048'],
            'audience_federation' => ['required', 'string', 'max:64'],
        ]);

        $execution = $acces->declarerEnvironnement(
            $reference,
            $donnees,
            (string) $request->attributes->get('gamad_entite'),
        );
        if ($execution['statut'] !== 201) {
            return back()->withInput()->withErrors(['environnement' => $this->motif($execution['corps'])]);
        }

        return redirect()
            ->route('console.produits.show', $reference)
            ->with('succes', 'Environnement déclaré.')
            ->with('preuve', $execution['corps']['preuve']);
    }

    public function fermerEnvironnement(
        Request $request,
        AccesProduits $acces,
        string $reference,
        int $id,
    ): RedirectResponse {
        $execution = $acces->fermerEnvironnement(
            $reference,
            $id,
            [],
            (string) $request->attributes->get('gamad_entite'),
        );
        if ($execution['statut'] !== 200) {
            return back()->withErrors(['environnement' => $this->motif($execution['corps'])]);
        }

        return redirect()
            ->route('console.produits.show', $reference)
            ->with('succes', 'Environnement fermé.')
            ->with('preuve', $execution['corps']['preuve']);
    }

    private function registre(): RegistreProduits
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

        return new RegistreProduits(
            $index,
            $registreIdentites,
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
