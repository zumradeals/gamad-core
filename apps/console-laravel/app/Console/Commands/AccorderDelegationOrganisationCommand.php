<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreIdentites\PolitiqueInscription;
use Gamad\RegistreNormes\Db;
use Gamad\RegistreOrganisations\PolitiqueOrganisations;
use Gamad\RegistrePolitiques\Magasin as PolitiquesMagasin;
use Gamad\RegistrePolitiques\RegistrePolitiques;
use Gamad\RegistreProduits\Magasin as ProduitsMagasin;
use Gamad\RegistreProduits\RegistreProduits;
use Illuminate\Console\Command;

/**
 * Procédure d'admission CORE-ORG-DELEGATION-001.
 *
 * Accorde à un produit précis, de façon explicite, révocable et auditable,
 * exactement deux capacités — jamais davantage :
 *
 *   1. inscrire une identité de type `organisation` (CAP-CORE-001, canal
 *      `PRODUIT_RECONNU` — voir `InscrireIdentite::executer()`) ;
 *   2. inscrire la fiche organisationnelle correspondante (CAP-CORE-002,
 *      `PolitiqueOrganisations::ACTION_INSCRIRE` — voir
 *      `AccesOrganisations::inscrire()`).
 *
 * Réutilise exclusivement `CTR-03` (CAP-CORE-004) et le registre gouverné
 * des politiques (CAP-CORE-007) : aucun système d'autorisation nouveau,
 * aucune table `product_permissions`. Deux politiques dédiées et minimales
 * portent ces deux règles — jamais `POL-ORGANISATIONS-V1` ni
 * `POL-INSCRIPTION-IDENTITES-V1` — pour ne jamais risquer d'altérer les
 * règles déjà réservées à `AUT-GAMAD-001` en reversionnant une politique
 * partagée : `RegistrePolitiques::activerVersion()` remplace intégralement
 * la version active précédente d'UNE MÊME politique ; deux politiques
 * distinctes n'interfèrent jamais entre elles.
 *
 * N'accorde jamais aucune autre action du registre des organisations : ni
 * modification, ni activation, ni suspension, ni dissolution, ni retrait —
 * seule la création (CREATE) est déléguée. Retrouver une organisation déjà
 * existante (ATTACH) reste une lecture (`AccesOrganisations::resoudreParIdentite()`),
 * jamais soumise à CTR-03.
 *
 * Générique par construction : la même commande, avec une autre référence de
 * produit, accorde exactement la même délégation à G-POS ou à tout autre
 * produit reconnu — sans code, sans condition, sans exception spécifiques à
 * ce produit.
 *
 * Idempotent : rejouer cette commande pour un produit déjà délégué ne crée
 * aucune version ni règle supplémentaire.
 */
final class AccorderDelegationOrganisationCommand extends Command
{
    protected $signature = 'core:organisations:accorder-delegation-satellite
                             {produit : référence du produit à admettre, ex. PRD-GAMAD-005}';

    protected $description = 'Accorde à un produit reconnu la délégation CORE-ORG-DELEGATION-001 (inscrire une identité « organisation » puis inscrire sa fiche CAP-CORE-002) — jamais aucune autre action du registre des organisations.';

    private const POLITIQUE_IDENTITES = 'POL-DELEGATION-IDENTITES-SATELLITES-V1';

    private const ACTION_IDENTITES = 'inscrire une identité';

    private const RESSOURCE_IDENTITES = 'organisation';

    private const POLITIQUE_ORGANISATIONS = 'POL-DELEGATION-ORGANISATIONS-SATELLITES-V1';

    public function handle(): int
    {
        $produit = trim((string) $this->argument('produit'));
        if ($produit === '') {
            $this->error('référence de produit absente.');

            return self::FAILURE;
        }

        try {
            $index = Db::connect();
            $registreIdentites = IdentiteMagasin::connecter();
            $ctr01 = new Ctr01($index, $registreIdentites);
            $registrePolitiques = new RegistrePolitiques($index, $registreIdentites, PolitiquesMagasin::connecter(), $ctr01);
            $registreProduits = new RegistreProduits($index, $registreIdentites, ProduitsMagasin::connecter(), $ctr01);
        } catch (\Throwable $e) {
            $this->error('Commande interrompue : '.$e->getMessage());

            return self::FAILURE;
        }

        if ($registreProduits->resoudreProduit($produit) === null) {
            $this->error("`{$produit}` n'a aucune fiche CAP-CORE-011 : rien à déléguer à un produit non inscrit.");

            return self::FAILURE;
        }

        $acteur = PolitiqueInscription::AUTORITE_INSCRIPTION;

        $identites = $this->accorder(
            $registrePolitiques,
            $acteur,
            $produit,
            self::POLITIQUE_IDENTITES,
            "Délégation satellite — inscription d'identité (CAP-CORE-001, canal PRODUIT_RECONNU)",
            'CORE-ORG-DELEGATION-001 — auto-gouvernance de la délégation d’inscription d’identité',
            self::ACTION_IDENTITES,
            self::RESSOURCE_IDENTITES,
            sprintf(
                '`%s` inscrit une identité par le canal PRODUIT_RECONNU (assurance A1, jamais plus), sous '
                .'réserve d’être reconnu ACTIF au sens de CAP-CORE-011 au moment de l’appel '
                .'(Ctr01::produitReconnu()). CTR-03 ne filtre pas par ressource : la portée réelle de cette '
                .'règle est bornée par PolitiqueInscription::CANAUX[PRODUIT_RECONNU], pas par cette règle seule.',
                $produit,
            ),
        );
        if ($identites === null) {
            return self::FAILURE;
        }

        $organisations = $this->accorder(
            $registrePolitiques,
            $acteur,
            $produit,
            self::POLITIQUE_ORGANISATIONS,
            'Délégation satellite — inscription d’organisation (CAP-CORE-002)',
            'CORE-ORG-DELEGATION-001 — auto-gouvernance de la délégation d’inscription d’organisation',
            PolitiqueOrganisations::ACTION_INSCRIRE,
            null,
            sprintf(
                '`%s` inscrit une fiche organisationnelle (CREATE seulement — jamais modifier, activer, '
                .'suspendre, dissoudre ni retirer). Contrôle défensif supplémentaire dans '
                .'AccesOrganisations::produitDelegueInactif() : l’appel est refusé si `%s` n’est pas ACTIF au '
                .'sens de CAP-CORE-011, même si cette règle reste active.',
                $produit,
                $produit,
            ),
        );
        if ($organisations === null) {
            return self::FAILURE;
        }

        $this->newLine();
        $this->info("CORE-ORG-DELEGATION-001 — `{$produit}` : {$identites}");
        $this->info("CORE-ORG-DELEGATION-001 — `{$produit}` : {$organisations}");
        $this->line('Aucune autre action du registre des organisations n’a été accordée. Aucun système ACL nouveau créé.');

        return self::SUCCESS;
    }

    /**
     * Accorde une règle `PERMET(action, sujet=$produit)` dans une politique
     * dédiée, en conservant fidèlement toutes les règles déjà actives de
     * cette politique : chaque nouvelle version d'une politique CAP-CORE-007
     * remplace entièrement la précédente (`RegistrePolitiques::activerVersion()`),
     * aucune règle n'y survit implicitement.
     */
    private function accorder(
        RegistrePolitiques $registre,
        string $acteur,
        string $produit,
        string $politiqueReference,
        string $libelle,
        string $sourceReference,
        string $action,
        ?string $ressource,
        string $motif,
    ): ?string {
        $politiqueAdmin = $politiqueReference;
        $source = "core:organisations:accorder-delegation-satellite — {$politiqueReference}";

        if ($registre->resoudrePolitique($politiqueReference) === null) {
            $inscription = $registre->inscrirePolitique([
                'reference' => $politiqueReference,
                'libelle' => $libelle,
                'proprietaire_reference' => $acteur,
                'source_reference' => $sourceReference,
                'politique' => $politiqueAdmin, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "DELEGATION-{$politiqueReference}-INSCRIPTION-{$produit}",
            ]);
            if (isset($inscription['refus'])) {
                $this->error("{$politiqueReference} : inscription refusée — {$inscription['refus']} ({$inscription['detail']})");

                return null;
            }
            $this->info("{$politiqueReference} : inscrite.");
        }

        $active = $registre->resoudreVersionActive($politiqueReference);
        $reglesExistantes = $active['regles'] ?? [];

        foreach ($reglesExistantes as $r) {
            if ($r['effet'] === 'PERMET' && $r['action_reference'] === $action && $r['sujet_reference'] === $produit) {
                $this->line("{$politiqueReference} : `{$produit}` porte déjà cette délégation, aucun doublon créé.");

                return "déjà accordée ({$politiqueReference}).";
            }
        }

        $nouvelleVersion = $active === null ? '1.0.0' : $this->incrementer((string) $active['version']);

        $creation = $registre->creerVersion($politiqueReference, [
            'version' => $nouvelleVersion,
            'politique' => $politiqueAdmin, 'producteur' => $acteur, 'source' => $source,
            'preuve' => "DELEGATION-{$politiqueReference}-{$nouvelleVersion}-VERSION-{$produit}",
        ]);
        if (isset($creation['refus'])) {
            $this->error("{$politiqueReference} {$nouvelleVersion} : création refusée — {$creation['refus']}");

            return null;
        }

        $regles = [];
        foreach ($reglesExistantes as $r) {
            $regles[] = [
                'effet' => $r['effet'],
                'action_reference' => $r['action_reference'],
                'sujet_reference' => $r['sujet_reference'],
                'ressource_reference' => $r['ressource_reference'],
                'motif' => $r['motif'],
            ];
        }
        $regles[] = [
            'effet' => 'PERMET',
            'action_reference' => $action,
            'sujet_reference' => $produit,
            'ressource_reference' => $ressource,
            'motif' => $motif,
        ];

        $numero = 0;
        foreach ($regles as $r) {
            $numero++;
            $ajout = $registre->ajouterRegle($politiqueReference, $nouvelleVersion, [
                'effet' => $r['effet'],
                'action_reference' => $r['action_reference'],
                'sujet_reference' => $r['sujet_reference'],
                'ressource_reference' => $r['ressource_reference'],
                'motif' => $r['motif'],
                'politique' => $politiqueAdmin, 'producteur' => $acteur, 'source' => $source,
                'preuve' => "DELEGATION-{$politiqueReference}-{$nouvelleVersion}-REGLE-{$numero}-{$produit}",
            ]);
            if (isset($ajout['refus'])) {
                $this->error("{$politiqueReference} {$nouvelleVersion} : règle refusée — {$ajout['refus']} ({$ajout['detail']})");

                return null;
            }
        }

        $soumission = $registre->soumettreVersion($politiqueReference, $nouvelleVersion, [
            'politique' => $politiqueAdmin, 'producteur' => $acteur, 'source' => $source,
            'preuve' => "DELEGATION-{$politiqueReference}-{$nouvelleVersion}-SOUMISSION-{$produit}",
        ]);
        if (isset($soumission['refus'])) {
            $this->error("{$politiqueReference} {$nouvelleVersion} : soumission refusée — {$soumission['refus']}");

            return null;
        }

        $cas = array_map(static fn (array $r): array => [
            'sujet' => $r['sujet_reference'], 'action' => $r['action_reference'], 'attendu' => 'PERMIS',
        ], $regles);
        $simulation = $registre->simulerVersion($politiqueReference, $nouvelleVersion, [
            'jeu_reference' => "DELEGATION-{$politiqueReference}-{$nouvelleVersion}-{$produit}",
            'cas' => $cas,
            'politique' => $politiqueAdmin, 'producteur' => $acteur, 'source' => $source,
            'preuve' => "DELEGATION-{$politiqueReference}-{$nouvelleVersion}-SIMULATION-{$produit}",
        ]);
        if (isset($simulation['refus']) || ($simulation['resultat'] ?? null) !== 'REUSSIE') {
            $this->error("{$politiqueReference} {$nouvelleVersion} : simulation non réussie — ".json_encode($simulation));

            return null;
        }

        $activation = $registre->activerVersion($politiqueReference, $nouvelleVersion, [
            'politique' => $politiqueAdmin, 'producteur' => $acteur, 'source' => $source,
            'preuve' => "DELEGATION-{$politiqueReference}-{$nouvelleVersion}-ACTIVATION-{$produit}",
            'motif' => "admission de `{$produit}` à la délégation CORE-ORG-DELEGATION-001",
        ]);
        if (isset($activation['refus'])) {
            $this->error("{$politiqueReference} {$nouvelleVersion} : activation refusée — {$activation['refus']}");

            return null;
        }
        $this->info("{$politiqueReference} {$nouvelleVersion} : cycle → ACTIVE, `{$produit}` délégué.");

        return "{$politiqueReference} {$nouvelleVersion}.";
    }

    private function incrementer(string $version): string
    {
        $parties = explode('.', $version);
        if (count($parties) !== 3) {
            return '1.0.0';
        }
        $parties[2] = (string) ((int) $parties[2] + 1);

        return implode('.', $parties);
    }
}
