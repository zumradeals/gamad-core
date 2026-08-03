<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Vocabulaire\AccesVocabulaire;
use Gamad\RegistreAutorisation\Ctr03;
use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreIdentites\PolitiqueInscription;
use Gamad\RegistreNormes\BaselineOperationnelle;
use Gamad\RegistreNormes\Db;
use Gamad\RegistrePolitiques\Magasin as PolitiquesMagasin;
use Gamad\RegistreVocabulaire\Magasin as VocabulaireMagasin;
use Gamad\RegistreVocabulaire\PolitiqueVocabulaire;
use Gamad\RegistreVocabulaire\RegistreVocabulaire;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Écran d'administration du registre du vocabulaire canonique (CAP-CORE-010).
 *
 * Les lectures interrogent directement le registre ; toute écriture passe par
 * `AccesVocabulaire`, le même cas d'usage gouverné que l'API v1.
 */
final class VocabulaireConsoleController
{
    public function index(Request $request): View
    {
        $acteur = (string) $request->attributes->get('gamad_entite');
        $autorite = $acteur === PolitiqueInscription::AUTORITE_INSCRIPTION;

        $tous = $this->registre()->listerVocabulaires();
        $vocabulaires = array_values(array_filter(
            $tous,
            static fn (array $v): bool => $v['version_active'] !== null
                || $autorite
                || $v['proprietaire_reference'] === $acteur,
        ));

        return view('vocabulaires.index', ['vocabulaires' => $vocabulaires, 'total' => count($vocabulaires), 'autorite' => $autorite]);
    }

    public function create(Request $request): View
    {
        $acteur = (string) $request->attributes->get('gamad_entite');
        $decision = (new Ctr03(PolitiquesMagasin::connecter()))
            ->autoriser($acteur, PolitiqueVocabulaire::ACTION_INSCRIRE, null);

        return view('vocabulaires.create', [
            'acteur' => $acteur, 'decision' => $decision,
            'inscriptionDisponible' => $decision['decision'] === 'PERMIS',
            'portees' => PolitiqueVocabulaire::PORTEES,
        ]);
    }

    public function store(Request $request, AccesVocabulaire $acces): RedirectResponse
    {
        $donnees = $request->validate([
            'reference' => ['required', 'string', 'max:64'],
            'namespace' => ['required', 'string', 'max:128'],
            'nom' => ['required', 'string', 'max:255'],
            'domaine' => ['required', 'string', 'max:128'],
            'portee' => ['required', 'string', 'in:' . implode(',', PolitiqueVocabulaire::PORTEES)],
            'proprietaire_reference' => ['required', 'string', 'max:64'],
            'source_reference' => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $execution = $acces->inscrire($donnees, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 201) {
            return back()->withInput()->withErrors(['inscription' => $this->motif($execution['corps'])]);
        }

        $reference = (string) $execution['corps']['resultat']['reference'];

        return redirect()->route('console.vocabulaires.show', $reference)
            ->with('succes', 'Vocabulaire inscrit.')->with('preuve', $execution['corps']['preuve']);
    }

    public function show(Request $request, string $reference): View
    {
        $registre = $this->registre();
        $vocabulaire = $registre->resoudreVocabulaire($reference);
        abort_if($vocabulaire === null, 404);

        $acteur = (string) $request->attributes->get('gamad_entite');
        $autorite = $acteur === PolitiqueInscription::AUTORITE_INSCRIPTION;
        abort_unless($vocabulaire['version_active'] !== null || $autorite || $vocabulaire['proprietaire_reference'] === $acteur, 404);

        return view('vocabulaires.show', [
            'vocabulaire' => $vocabulaire,
            'versions' => $registre->listerVersions($reference),
            'historique' => $registre->resoudreHistorique($reference),
            'autorite' => $autorite,
        ]);
    }

    public function creerVersion(Request $request, AccesVocabulaire $acces, string $reference): RedirectResponse
    {
        $donnees = $request->validate([
            'version' => ['required', 'string', 'max:32'],
            'date_effet_prevue' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $execution = $acces->creerVersion($reference, $donnees, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 201) {
            return back()->withInput()->withErrors(['version' => $this->motif($execution['corps'])]);
        }

        return redirect()->route('console.vocabulaires.version', [$reference, $donnees['version']])
            ->with('succes', 'Version créée en BROUILLON.')->with('preuve', $execution['corps']['preuve']);
    }

    public function versionShow(Request $request, string $reference, string $version): View
    {
        $registre = $this->registre();
        $vocabulaire = $registre->resoudreVocabulaire($reference);
        abort_if($vocabulaire === null, 404);
        $v = $registre->resoudreVersion($reference, $version);
        abort_if($v === null, 404);

        $acteur = (string) $request->attributes->get('gamad_entite');
        $autorite = $acteur === PolitiqueInscription::AUTORITE_INSCRIPTION;
        abort_unless($autorite || $vocabulaire['proprietaire_reference'] === $acteur, 404);

        return view('vocabulaires.version', [
            'vocabulaire' => $vocabulaire, 'version' => $v, 'autorite' => $autorite,
            'compatibilites' => $registre->resoudreCompatibilite($reference, $version),
            'conformites' => $registre->resoudreConformite($reference, $version),
            'typesSemantiques' => PolitiqueVocabulaire::TYPES_SEMANTIQUES,
            'locales' => PolitiqueVocabulaire::LOCALES,
            'typesAlias' => PolitiqueVocabulaire::TYPES_ALIAS,
            'typesProjection' => PolitiqueVocabulaire::TYPES_PROJECTION,
            'resultatsConformite' => PolitiqueVocabulaire::RESULTATS_CONFORMITE,
            'typesConsommateur' => PolitiqueVocabulaire::TYPES_CONSOMMATEUR,
        ]);
    }

    public function ajouterTerme(Request $request, AccesVocabulaire $acces, string $reference, string $version): RedirectResponse
    {
        $donnees = $request->validate([
            'reference' => ['required', 'string', 'max:128'],
            'code' => ['required', 'string', 'max:64'],
            'definition' => ['required', 'string', 'max:2000'],
            'type_semantique' => ['required', 'string', 'in:' . implode(',', PolitiqueVocabulaire::TYPES_SEMANTIQUES)],
            'ordre_affichage' => ['nullable', 'integer'],
        ]);

        $execution = $acces->ajouterTerme($reference, $version, $donnees, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 201) {
            return back()->withInput()->withErrors(['terme' => $this->motif($execution['corps'])]);
        }

        return redirect()->route('console.vocabulaires.version', [$reference, $version])
            ->with('succes', 'Terme ajouté.')->with('preuve', $execution['corps']['preuve']);
    }

    public function ajouterLibelle(Request $request, AccesVocabulaire $acces, string $reference, string $version): RedirectResponse
    {
        $donnees = $request->validate([
            'terme_reference' => ['required', 'string', 'max:128'],
            'locale' => ['required', 'string', 'in:' . implode(',', PolitiqueVocabulaire::LOCALES)],
            'libelle' => ['required', 'string', 'max:255'],
            'description_courte' => ['nullable', 'string', 'max:500'],
            'principal' => ['nullable', 'boolean'],
        ]);
        $termeReference = (string) $donnees['terme_reference'];
        unset($donnees['terme_reference']);

        $execution = $acces->ajouterLibelle($termeReference, $donnees, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 201) {
            return back()->withInput()->withErrors(['libelle' => $this->motif($execution['corps'])]);
        }

        return redirect()->route('console.vocabulaires.version', [$reference, $version])
            ->with('succes', 'Libellé ajouté.')->with('preuve', $execution['corps']['preuve']);
    }

    public function ajouterAlias(Request $request, AccesVocabulaire $acces, string $reference, string $version): RedirectResponse
    {
        $donnees = $request->validate([
            'terme_reference' => ['required', 'string', 'max:128'],
            'alias' => ['required', 'string', 'max:128'],
            'type_alias' => ['required', 'string', 'in:' . implode(',', PolitiqueVocabulaire::TYPES_ALIAS)],
            'source_reference' => ['required', 'string', 'max:256'],
        ]);
        $termeReference = (string) $donnees['terme_reference'];
        unset($donnees['terme_reference']);

        $execution = $acces->ajouterAlias($termeReference, $donnees, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 201) {
            return back()->withInput()->withErrors(['alias' => $this->motif($execution['corps'])]);
        }

        return redirect()->route('console.vocabulaires.version', [$reference, $version])
            ->with('succes', 'Alias ajouté.')->with('preuve', $execution['corps']['preuve']);
    }

    public function evoluerTerme(Request $request, AccesVocabulaire $acces, string $reference, string $version): RedirectResponse
    {
        $donnees = $request->validate([
            'ancienne_reference' => ['required', 'string', 'max:128'],
            'reference' => ['required', 'string', 'max:128'],
            'code' => ['nullable', 'string', 'max:64'],
            'definition' => ['nullable', 'string', 'max:2000'],
            'type_semantique' => ['nullable', 'string', 'in:' . implode(',', PolitiqueVocabulaire::TYPES_SEMANTIQUES)],
        ]);
        $ancienneReference = (string) $donnees['ancienne_reference'];
        unset($donnees['ancienne_reference']);

        $execution = $acces->evoluerTerme($ancienneReference, $version, $donnees, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 201) {
            return back()->withInput()->withErrors(['evolution' => $this->motif($execution['corps'])]);
        }

        return redirect()->route('console.vocabulaires.version', [$reference, $version])
            ->with('succes', 'Terme reconduit dans cette version.')->with('preuve', $execution['corps']['preuve']);
    }

    public function soumettre(Request $request, AccesVocabulaire $acces, string $reference, string $version): RedirectResponse
    {
        $execution = $acces->soumettreVersion($reference, $version, [], (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 200) {
            return back()->withErrors(['version' => $this->motif($execution['corps'])]);
        }

        return redirect()->route('console.vocabulaires.version', [$reference, $version])
            ->with('succes', 'Version soumise à validation. Elle est désormais immuable.')
            ->with('preuve', $execution['corps']['preuve']);
    }

    public function analyser(Request $request, AccesVocabulaire $acces, string $reference, string $version): RedirectResponse
    {
        $execution = $acces->analyserCompatibilite($reference, $version, [], (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 201) {
            return back()->withErrors(['analyse' => $this->motif($execution['corps'])]);
        }
        $resultat = $execution['corps']['resultat']['resultat'] ?? 'INCONNU';

        return redirect()->route('console.vocabulaires.version', [$reference, $version])
            ->with('succes', "Analyse de compatibilité : {$resultat}.")->with('preuve', $execution['corps']['preuve']);
    }

    public function genererProjection(Request $request, AccesVocabulaire $acces, string $reference, string $version): RedirectResponse
    {
        $donnees = $request->validate(['type_projection' => ['required', 'string', 'in:' . implode(',', PolitiqueVocabulaire::TYPES_PROJECTION)]]);
        $execution = $acces->genererProjection($reference, $version, $donnees, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 201) {
            return back()->withErrors(['projection' => $this->motif($execution['corps'])]);
        }

        return redirect()->route('console.vocabulaires.version', [$reference, $version])
            ->with('succes', 'Projection générée.')->with('preuve', $execution['corps']['preuve']);
    }

    public function enregistrerConformite(Request $request, AccesVocabulaire $acces, string $reference, string $version): RedirectResponse
    {
        $donnees = $request->validate([
            'resultat' => ['required', 'string', 'in:' . implode(',', PolitiqueVocabulaire::RESULTATS_CONFORMITE)],
            'consommateur_reference' => ['required', 'string', 'max:64'],
            'type_consommateur' => ['required', 'string', 'in:' . implode(',', PolitiqueVocabulaire::TYPES_CONSOMMATEUR)],
            'commit_reference' => ['nullable', 'string', 'max:128'],
        ]);

        $execution = $acces->enregistrerConformite($reference, $version, $donnees, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 201) {
            return back()->withInput()->withErrors(['conformite' => $this->motif($execution['corps'])]);
        }

        return redirect()->route('console.vocabulaires.version', [$reference, $version])
            ->with('succes', 'Conformité enregistrée.')->with('preuve', $execution['corps']['preuve']);
    }

    public function activer(Request $request, AccesVocabulaire $acces, string $reference, string $version): RedirectResponse
    {
        $donnees = $request->validate(['motif' => ['nullable', 'string', 'max:500']]);
        $execution = $acces->activerVersion($reference, $version, $donnees, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 200) {
            return back()->withInput()->withErrors(['version' => $this->motif($execution['corps'])]);
        }

        return redirect()->route('console.vocabulaires.show', $reference)
            ->with('succes', 'Version activée.')->with('preuve', $execution['corps']['preuve']);
    }

    public function deprecier(Request $request, AccesVocabulaire $acces, string $reference, string $version): RedirectResponse
    {
        $donnees = $request->validate(['motif' => ['nullable', 'string', 'max:500']]);
        $execution = $acces->deprecierVersion($reference, $version, $donnees, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 200) {
            return back()->withErrors(['version' => $this->motif($execution['corps'])]);
        }

        return redirect()->route('console.vocabulaires.show', $reference)
            ->with('succes', 'Version dépréciée.')->with('preuve', $execution['corps']['preuve']);
    }

    public function retirer(Request $request, AccesVocabulaire $acces, string $reference, string $version): RedirectResponse
    {
        $donnees = $request->validate(['motif' => ['nullable', 'string', 'max:500']]);
        $execution = $acces->retirerVersion($reference, $version, $donnees, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 200) {
            return back()->withErrors(['version' => $this->motif($execution['corps'])]);
        }

        return redirect()->route('console.vocabulaires.show', $reference)
            ->with('succes', 'Version retirée. Son historique reste consultable.')
            ->with('preuve', $execution['corps']['preuve']);
    }

    public function deprecierTerme(Request $request, AccesVocabulaire $acces, string $reference, string $version): RedirectResponse
    {
        $donnees = $request->validate([
            'terme_reference' => ['required', 'string', 'max:128'],
            'remplace_par_reference' => ['nullable', 'string', 'max:128'],
        ]);
        $termeReference = (string) $donnees['terme_reference'];
        unset($donnees['terme_reference']);

        $execution = $acces->deprecierTerme($termeReference, $donnees, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 200) {
            return back()->withErrors(['terme' => $this->motif($execution['corps'])]);
        }

        return redirect()->route('console.vocabulaires.version', [$reference, $version])
            ->with('succes', 'Terme déprécié.')->with('preuve', $execution['corps']['preuve']);
    }

    public function retirerTerme(Request $request, AccesVocabulaire $acces, string $reference, string $version): RedirectResponse
    {
        $donnees = $request->validate(['terme_reference' => ['required', 'string', 'max:128']]);
        $termeReference = (string) $donnees['terme_reference'];
        unset($donnees['terme_reference']);

        $execution = $acces->retirerTerme($termeReference, $donnees, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 200) {
            return back()->withErrors(['terme' => $this->motif($execution['corps'])]);
        }

        return redirect()->route('console.vocabulaires.version', [$reference, $version])
            ->with('succes', 'Terme retiré.')->with('preuve', $execution['corps']['preuve']);
    }

    private function registre(): RegistreVocabulaire
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

        return new RegistreVocabulaire($index, $registreIdentites, VocabulaireMagasin::connecter(), new Ctr01($index, $registreIdentites));
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
