<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Politiques\AccesPolitiques;
use Gamad\RegistreAutorisation\Ctr03;
use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreIdentites\PolitiqueInscription;
use Gamad\RegistreNormes\BaselineOperationnelle;
use Gamad\RegistreNormes\Db;
use Gamad\RegistrePolitiques\Magasin as PolitiquesMagasin;
use Gamad\RegistrePolitiques\PolitiqueAdministration;
use Gamad\RegistrePolitiques\RegistrePolitiques;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Écran d'administration du registre des politiques (CAP-CORE-007).
 *
 * Les lectures interrogent directement le registre ; toute écriture passe par
 * `AccesPolitiques`, le même cas d'usage gouverné que l'API v1 — y compris
 * pour la politique `POL-POLITIQUES-V1` qui gouverne cet écran lui-même.
 */
final class PolitiqueConsoleController
{
    public function index(Request $request): View
    {
        $acteur = (string) $request->attributes->get('gamad_entite');
        $autorite = $acteur === PolitiqueInscription::AUTORITE_INSCRIPTION;

        $tous = $this->registre()->listerPolitiques();
        $politiques = array_values(array_filter(
            $tous,
            static fn (array $p): bool => $p['version_active'] !== null
                || $autorite
                || $p['proprietaire_reference'] === $acteur,
        ));

        return view('politiques.index', [
            'politiques' => $politiques,
            'total' => count($politiques),
            'autorite' => $autorite,
        ]);
    }

    public function create(Request $request): View
    {
        $acteur = (string) $request->attributes->get('gamad_entite');
        $decision = (new Ctr03(PolitiquesMagasin::connecter()))
            ->autoriser($acteur, PolitiqueAdministration::ACTION_INSCRIRE, null);

        return view('politiques.create', [
            'acteur' => $acteur,
            'decision' => $decision,
            'inscriptionDisponible' => $decision['decision'] === 'PERMIS',
        ]);
    }

    public function store(Request $request, AccesPolitiques $acces): RedirectResponse
    {
        $donnees = $request->validate([
            'reference' => ['required', 'string', 'max:64'],
            'libelle' => ['required', 'string', 'max:255'],
            'domaine' => ['nullable', 'string', 'max:128'],
            'proprietaire_reference' => ['required', 'string', 'max:64'],
            'source_reference' => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $execution = $acces->inscrire($donnees, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 201) {
            return back()->withInput()->withErrors(['inscription' => $this->motif($execution['corps'])]);
        }

        $reference = (string) $execution['corps']['resultat']['reference'];

        return redirect()
            ->route('console.politiques.show', $reference)
            ->with('succes', 'Politique inscrite.')
            ->with('preuve', $execution['corps']['preuve']);
    }

    public function show(Request $request, string $reference): View
    {
        $registre = $this->registre();
        $politique = $registre->resoudrePolitique($reference);
        abort_if($politique === null, 404);

        $acteur = (string) $request->attributes->get('gamad_entite');
        $autorite = $acteur === PolitiqueInscription::AUTORITE_INSCRIPTION;
        abort_unless(
            $politique['version_active'] !== null || $autorite || $politique['proprietaire_reference'] === $acteur,
            404,
        );

        return view('politiques.show', [
            'politique' => $politique,
            'versions' => $registre->listerVersions($reference),
            'historique' => $registre->resoudreHistorique($reference),
            'autorite' => $autorite,
        ]);
    }

    public function creerVersion(Request $request, AccesPolitiques $acces, string $reference): RedirectResponse
    {
        $donnees = $request->validate([
            'version' => ['required', 'string', 'max:32'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $execution = $acces->creerVersion($reference, $donnees, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 201) {
            return back()->withInput()->withErrors(['version' => $this->motif($execution['corps'])]);
        }

        return redirect()
            ->route('console.politiques.version', [$reference, $donnees['version']])
            ->with('succes', 'Version créée en BROUILLON.')
            ->with('preuve', $execution['corps']['preuve']);
    }

    public function versionShow(Request $request, string $reference, string $version): View
    {
        $registre = $this->registre();
        $politique = $registre->resoudrePolitique($reference);
        abort_if($politique === null, 404);
        $v = $registre->resoudreVersion($reference, $version);
        abort_if($v === null, 404);

        $acteur = (string) $request->attributes->get('gamad_entite');
        $autorite = $acteur === PolitiqueInscription::AUTORITE_INSCRIPTION;
        abort_unless($autorite || $politique['proprietaire_reference'] === $acteur, 404);

        return view('politiques.version', [
            'politique' => $politique,
            'version' => $v,
            'autorite' => $autorite,
            'effets' => PolitiqueAdministration::EFFETS,
        ]);
    }

    public function ajouterRegle(Request $request, AccesPolitiques $acces, string $reference, string $version): RedirectResponse
    {
        $donnees = $request->validate([
            'effet' => ['required', 'string', 'in:PERMET,REFUSE'],
            'action_reference' => ['required', 'string', 'max:256'],
            'sujet_reference' => ['nullable', 'string', 'max:64'],
            'motif' => ['required', 'string', 'max:2000'],
        ]);

        $execution = $acces->ajouterRegle($reference, $version, $donnees, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 201) {
            return back()->withInput()->withErrors(['regle' => $this->motif($execution['corps'])]);
        }

        return redirect()
            ->route('console.politiques.version', [$reference, $version])
            ->with('succes', 'Règle ajoutée.')
            ->with('preuve', $execution['corps']['preuve']);
    }

    public function soumettre(Request $request, AccesPolitiques $acces, string $reference, string $version): RedirectResponse
    {
        $execution = $acces->soumettreVersion($reference, $version, [], (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 200) {
            return back()->withErrors(['version' => $this->motif($execution['corps'])]);
        }

        return redirect()
            ->route('console.politiques.version', [$reference, $version])
            ->with('succes', 'Version soumise à validation. Elle est désormais immuable.')
            ->with('preuve', $execution['corps']['preuve']);
    }

    public function simuler(Request $request, AccesPolitiques $acces, string $reference, string $version): RedirectResponse
    {
        $donnees = $request->validate([
            'jeu_reference' => ['required', 'string', 'max:128'],
            'sujet' => ['required', 'array'],
            'action' => ['required', 'array'],
            'attendu' => ['required', 'array'],
        ]);
        $cas = [];
        foreach ($donnees['sujet'] as $i => $sujet) {
            if (trim((string) $sujet) === '' || !isset($donnees['action'][$i], $donnees['attendu'][$i])) {
                continue;
            }
            $cas[] = ['sujet' => $sujet, 'action' => $donnees['action'][$i], 'attendu' => $donnees['attendu'][$i]];
        }

        $execution = $acces->simulerVersion(
            $reference, $version,
            ['jeu_reference' => $donnees['jeu_reference'], 'cas' => $cas],
            (string) $request->attributes->get('gamad_entite'),
        );
        if ($execution['statut'] !== 201) {
            return back()->withInput()->withErrors(['simulation' => $this->motif($execution['corps'])]);
        }

        $resultat = $execution['corps']['resultat']['resultat'] ?? 'INCONNU';

        return redirect()
            ->route('console.politiques.version', [$reference, $version])
            ->with('succes', "Simulation exécutée : {$resultat}.")
            ->with('preuve', $execution['corps']['preuve']);
    }

    public function activer(Request $request, AccesPolitiques $acces, string $reference, string $version): RedirectResponse
    {
        $donnees = $request->validate(['motif' => ['nullable', 'string', 'max:500']]);
        $execution = $acces->activerVersion($reference, $version, $donnees, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 200) {
            return back()->withErrors(['version' => $this->motif($execution['corps'])]);
        }

        return redirect()
            ->route('console.politiques.show', $reference)
            ->with('succes', 'Version activée.')
            ->with('preuve', $execution['corps']['preuve']);
    }

    public function suspendre(Request $request, AccesPolitiques $acces, string $reference, string $version): RedirectResponse
    {
        $donnees = $request->validate(['motif' => ['nullable', 'string', 'max:500']]);
        $execution = $acces->suspendreVersion($reference, $version, $donnees, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 200) {
            return back()->withErrors(['version' => $this->motif($execution['corps'])]);
        }

        return redirect()
            ->route('console.politiques.show', $reference)
            ->with('succes', 'Version suspendue. Elle ne permet plus rien immédiatement.')
            ->with('preuve', $execution['corps']['preuve']);
    }

    public function retirer(Request $request, AccesPolitiques $acces, string $reference): RedirectResponse
    {
        $donnees = $request->validate(['motif' => ['nullable', 'string', 'max:500']]);
        $execution = $acces->retirer($reference, $donnees, (string) $request->attributes->get('gamad_entite'));
        if ($execution['statut'] !== 200) {
            return back()->withErrors(['politique' => $this->motif($execution['corps'])]);
        }

        return redirect()
            ->route('console.politiques.show', $reference)
            ->with('succes', 'Politique retirée. Son historique reste consultable.')
            ->with('preuve', $execution['corps']['preuve']);
    }

    private function registre(): RegistrePolitiques
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

        return new RegistrePolitiques(
            $index,
            $registreIdentites,
            PolitiquesMagasin::connecter(),
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
