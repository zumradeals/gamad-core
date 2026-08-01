<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Federation\AccesSatellites;
use Gamad\RegistreAcces\Ctr16;
use Gamad\RegistreAcces\Magasin as AccesMagasin;
use Gamad\RegistreFederation\Federation;
use Gamad\RegistreFederation\PolitiqueFederation;
use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreIdentites\PolitiqueInscription;
use Gamad\RegistreNormes\BaselineOperationnelle;
use Gamad\RegistreNormes\Db;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Écran d'administration de la fédération (CAP-CORE-022).
 *
 * La console ne contourne rien : ouvrir et révoquer passent par le même cas
 * d'usage que l'API v1, donc par la décision CAP-CORE-004 et par la preuve
 * CAP-CORE-013. Elle n'ajoute qu'une lecture — la liste des porteurs — bornée
 * au satellite concerné et à l'autorité.
 */
final class SatelliteConsoleController
{
    public function index(Request $request): View
    {
        $federation = $this->federation();
        $acteur = (string) $request->attributes->get('gamad_entite');
        $autorite = $acteur === PolitiqueInscription::AUTORITE_INSCRIPTION;

        $satellites = [];
        foreach ($federation->catalogueProduits() as $produit) {
            $porteurs = $federation->resoudrePorteurs((string) $produit['reference'], $acteur);
            $satellites[] = $produit + [
                'lisible' => !isset($porteurs['refus']),
                'acces_actifs' => isset($porteurs['refus']) ? null : count($porteurs['porteurs']),
            ];
        }

        return view('satellites.index', [
            'satellites' => $satellites,
            'acteur' => $acteur,
            'autorite' => $autorite,
        ]);
    }

    public function show(Request $request, string $produit): View
    {
        $federation = $this->federation();
        $catalogue = array_column($federation->catalogueProduits(), null, 'reference');
        abort_if(!isset($catalogue[$produit]), 404);

        $acteur = (string) $request->attributes->get('gamad_entite');
        $porteurs = $federation->resoudrePorteurs($produit, $acteur);

        $identifiants = (new Ctr16(AccesMagasin::connecter()))->attester($produit);
        $actifs = array_filter(
            $identifiants['authentificateurs'],
            static fn (array $a): bool => $a['etat'] === 'ACTIF',
        );

        return view('satellites.show', [
            'satellite' => $catalogue[$produit],
            'porteurs' => $porteurs['porteurs'] ?? [],
            'lisible' => !isset($porteurs['refus']),
            'motifIllisible' => $porteurs['detail'] ?? null,
            'identifiantsConfigures' => $actifs !== [],
            'peutAdministrer' => $acteur === PolitiqueInscription::AUTORITE_INSCRIPTION
                || $acteur === $produit,
            'adresseApi' => rtrim((string) config('app.url'), '/').'/api/v1',
            'relations' => PolitiqueInscription::RELATIONS_PRODUIT,
            'dureeJeton' => PolitiqueFederation::DUREE_JETON,
        ]);
    }

    public function ouvrir(Request $request, AccesSatellites $acces, string $produit): RedirectResponse
    {
        $donnees = $request->validate([
            'identite' => ['required', 'string', 'max:64'],
            'relation_type' => ['required', 'string', 'in:'.implode(',', PolitiqueInscription::RELATIONS_PRODUIT)],
        ]);

        $execution = $acces->ouvrir(
            $donnees['identite'],
            $produit,
            (string) $request->attributes->get('gamad_entite'),
            (string) $request->session()->get('gamad_session'),
            ['relation_type' => $donnees['relation_type']],
        );

        if ($execution['statut'] !== 201) {
            return back()->withInput()->withErrors([
                'acces' => $this->motif($execution['corps']),
            ]);
        }

        // Le jeton n'est montré qu'ici et qu'une fois. Il n'est pas remis en
        // session pour éviter qu'il ne survive au rechargement de la page.
        return redirect()
            ->route('console.satellites.show', $produit)
            ->with('succes', 'Accès ouvert pour '.$donnees['identite'].'.')
            ->with('jeton_federe', $execution['corps']['acces'])
            ->with('preuve', $execution['corps']['preuve']);
    }

    public function revoquer(Request $request, AccesSatellites $acces, string $produit): RedirectResponse
    {
        $donnees = $request->validate([
            'identite' => ['required', 'string', 'max:64'],
        ]);

        $execution = $acces->revoquer(
            $donnees['identite'],
            $produit,
            (string) $request->attributes->get('gamad_entite'),
        );

        if ($execution['statut'] !== 200) {
            return back()->withErrors(['acces' => $this->motif($execution['corps'])]);
        }

        $fermes = (int) ($execution['corps']['revocation']['jetons_fermes'] ?? 0);

        return redirect()
            ->route('console.satellites.show', $produit)
            ->with('succes', sprintf(
                'Accès révoqué pour %s. %d jeton(s) encore ouvert(s) ont été fermés.',
                $donnees['identite'],
                $fermes,
            ))
            ->with('preuve', $execution['corps']['preuve']);
    }

    private function federation(): Federation
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

        $registre = IdentiteMagasin::connecter();

        return new Federation(
            $index,
            $registre,
            AccesMagasin::connecter(),
            new Ctr01($index, $registre),
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
