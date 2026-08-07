<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Comptes\CreerCompteGamad;
use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreIdentites\PolitiqueInscription;
use Gamad\RegistreNormes\Db;
use Gamad\RegistrePolitiques\Magasin as PolitiquesMagasin;
use Gamad\RegistrePolitiques\PolitiqueAdministration;
use Gamad\RegistrePolitiques\RegistrePolitiques;
use Illuminate\Console\Command;

/**
 * Première délégation gouvernée de création de Comptes GAMAD.
 *
 * Elle n'altère pas POL-INSCRIPTION-IDENTITES-V1, qui reste une reprise
 * historique. Une politique distincte donne seulement au portail DG AFRIQUE
 * la capacité `créer un Compte GAMAD`. Tout futur produit devra recevoir sa
 * propre règle dans une nouvelle version gouvernée de cette politique.
 */
final class BootstrapComptesGamadCommand extends Command
{
    protected $signature = 'core:comptes:bootstrap';

    protected $description = 'Inscrit et active la première politique de délégation de création des Comptes GAMAD.';

    private const POLITIQUE = 'POL-COMPTES-GAMAD-V1';
    private const VERSION = '1.0.0';
    private const PRODUIT = 'PRD-GAMAD-005';
    private const SOURCE = 'CAP-PORTAL-003 — délégation gouvernée de création des Comptes GAMAD';

    public function handle(): int
    {
        try {
            $index = Db::connect();
            $registreIdentites = IdentiteMagasin::connecter();
            $ctr01 = new Ctr01($index, $registreIdentites);
            $identiteProduit = $ctr01->resoudreIdentite(self::PRODUIT);
            if (!is_array($identiteProduit) || ($identiteProduit['type'] ?? null) !== 'produit') {
                $this->error(self::PRODUIT . ' doit exister comme identité canonique de type produit avant cette délégation.');
                return self::FAILURE;
            }

            $registre = new RegistrePolitiques(
                $index,
                $registreIdentites,
                PolitiquesMagasin::connecter(),
                $ctr01,
            );
        } catch (\Throwable $e) {
            $this->error('Bootstrap Compte GAMAD interrompu : ' . $e->getMessage());
            return self::FAILURE;
        }

        $acteur = PolitiqueInscription::AUTORITE_INSCRIPTION;
        $gouvernance = PolitiqueAdministration::POLITIQUE;

        if ($registre->resoudrePolitique($gouvernance) === null) {
            $this->error('La politique d’administration POL-POLITIQUES-V1 est absente. Exécuter d’abord core:politiques:bootstrap.');
            return self::FAILURE;
        }

        if ($registre->resoudrePolitique(self::POLITIQUE) === null) {
            $inscription = $registre->inscrirePolitique([
                'reference' => self::POLITIQUE,
                'libelle' => 'Délégation de création des Comptes GAMAD',
                'domaine' => 'IDENTITE_ET_ACCES',
                'proprietaire_reference' => $acteur,
                'source_reference' => self::SOURCE,
                'description' => 'Détermine explicitement quels produits peuvent créer une personne canonique, son identifiant de résolution et son premier authentificateur.',
                'politique' => $gouvernance,
                'producteur' => $acteur,
                'source' => self::SOURCE,
                'preuve' => 'BOOT-COMPTES-GAMAD-POLITIQUE',
            ]);
            if (isset($inscription['refus'])) {
                $this->error('Inscription de la politique refusée : ' . json_encode($inscription, JSON_UNESCAPED_UNICODE));
                return self::FAILURE;
            }
            $this->info(self::POLITIQUE . ' : politique inscrite.');
        }

        $existante = $registre->resoudreVersion(self::POLITIQUE, self::VERSION);
        if (is_array($existante)) {
            if (($existante['etat'] ?? null) === 'ACTIVE') {
                $this->info(self::POLITIQUE . ' ' . self::VERSION . ' : déjà active, aucune modification.');
                return self::SUCCESS;
            }

            $this->error(
                self::POLITIQUE . ' ' . self::VERSION
                . ' existe dans un état non actif. Revue gouvernée requise avant reprise automatique.'
            );
            return self::FAILURE;
        }

        $commun = [
            'politique' => $gouvernance,
            'producteur' => $acteur,
            'source' => self::SOURCE,
        ];

        $creation = $registre->creerVersion(self::POLITIQUE, [
            ...$commun,
            'version' => self::VERSION,
            'description' => 'Première délégation : DG AFRIQUE Portal uniquement.',
            'preuve' => 'BOOT-COMPTES-GAMAD-VERSION',
        ]);
        if (isset($creation['refus'])) {
            $this->error('Création de version refusée : ' . json_encode($creation, JSON_UNESCAPED_UNICODE));
            return self::FAILURE;
        }

        $regle = $registre->ajouterRegle(self::POLITIQUE, self::VERSION, [
            ...$commun,
            'effet' => 'PERMET',
            'action_reference' => CreerCompteGamad::ACTION,
            'sujet_reference' => self::PRODUIT,
            'ressource_type' => 'personne',
            'motif' => 'DG AFRIQUE Portal est explicitement habilité à créer un Compte GAMAD pour une personne. Cette permission ne vaut pas permission générique d’inscrire une identité.',
            'preuve' => 'BOOT-COMPTES-GAMAD-REGLE-001',
        ]);
        if (isset($regle['refus'])) {
            $this->error('Ajout de règle refusé : ' . json_encode($regle, JSON_UNESCAPED_UNICODE));
            return self::FAILURE;
        }

        $soumission = $registre->soumettreVersion(self::POLITIQUE, self::VERSION, [
            ...$commun,
            'preuve' => 'BOOT-COMPTES-GAMAD-SOUMISSION',
        ]);
        if (isset($soumission['refus'])) {
            $this->error('Soumission refusée : ' . json_encode($soumission, JSON_UNESCAPED_UNICODE));
            return self::FAILURE;
        }

        $simulation = $registre->simulerVersion(self::POLITIQUE, self::VERSION, [
            ...$commun,
            'jeu_reference' => 'SIM-COMPTES-GAMAD-V1',
            'cas' => [
                [
                    'sujet' => self::PRODUIT,
                    'action' => CreerCompteGamad::ACTION,
                    'ressource' => 'personne',
                    'attendu' => 'PERMIS',
                ],
                [
                    'sujet' => 'PRD-GAMAD-NON-AUTORISE',
                    'action' => CreerCompteGamad::ACTION,
                    'ressource' => 'personne',
                    'attendu' => 'REFUSE',
                ],
            ],
            'preuve' => 'BOOT-COMPTES-GAMAD-SIMULATION',
        ]);
        if (isset($simulation['refus']) || ($simulation['resultat'] ?? null) !== 'REUSSIE') {
            $this->error('Simulation non réussie : ' . json_encode($simulation, JSON_UNESCAPED_UNICODE));
            return self::FAILURE;
        }

        $activation = $registre->activerVersion(self::POLITIQUE, self::VERSION, [
            ...$commun,
            'preuve' => 'BOOT-COMPTES-GAMAD-ACTIVATION',
            'motif' => 'Activation de la première délégation explicite de création de Compte GAMAD.',
        ]);
        if (isset($activation['refus'])) {
            $this->error('Activation refusée : ' . json_encode($activation, JSON_UNESCAPED_UNICODE));
            return self::FAILURE;
        }

        $this->info(self::POLITIQUE . ' ' . self::VERSION . ' : ACTIVE pour ' . self::PRODUIT . '.');
        return self::SUCCESS;
    }
}
