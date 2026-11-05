<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreIdentites\Magasin as IdentitesMagasin;
use Gamad\RegistreIdentites\PolitiqueInscription;
use Gamad\RegistreNormes\Db;
use Gamad\RegistrePolitiques\Magasin as PolitiquesMagasin;
use Gamad\RegistrePolitiques\RegistrePolitiques;
use Illuminate\Console\Command;

/**
 * R01 : admission explicite de l'autorité à la console.
 * Ne s'exécute jamais automatiquement au démarrage ou au déploiement.
 * Aucune modification du bootstrap historique ni permission implicite.
 */
final class AccorderAccesConsoleCommand extends Command
{
    protected $signature = 'core:console:accorder-acces-administratif';
    protected $description = 'Active via CAP-CORE-007 la permission de console de l’autorité d’inscription uniquement.';

    private const POLITIQUE = 'POL-ACCES-CONSOLE-ADMIN-V1';
    private const ACTION = 'consulter la console';

    public function handle(): int
    {
        $acteur = PolitiqueInscription::AUTORITE_INSCRIPTION;
        $source = 'R01 — admission administrative explicite de la console';
        $meta = static fn (string $preuve): array => [
            'politique' => self::POLITIQUE,
            'producteur' => $acteur,
            'source' => $source,
            'preuve' => $preuve,
        ];

        try {
            $index = Db::connect();
            $identites = IdentitesMagasin::connecter();
            $registre = new RegistrePolitiques(
                $index, $identites, PolitiquesMagasin::connecter(), new Ctr01($index, $identites)
            );

            $active = $registre->resoudreVersionActive(self::POLITIQUE);
            if ($active !== null) {
                $regles = $active['regles'] ?? [];
                if (count($regles) === 1
                    && $regles[0]['effet'] === 'PERMET'
                    && $regles[0]['action_reference'] === 'consulter-la-console'
                    && $regles[0]['sujet_reference'] === $acteur) {
                    $this->info('R01 : autorisation déjà active, aucune mutation.');
                    return self::SUCCESS;
                }
                $this->error('Politique R01 déjà active avec des règles différentes : intervention manuelle requise.');
                return self::FAILURE;
            }

            if ($registre->resoudrePolitique(self::POLITIQUE) !== null) {
                $this->error('Politique R01 existante mais inactive : aucun contournement de son cycle.');
                return self::FAILURE;
            }

            $inscription = $registre->inscrirePolitique([
                'reference' => self::POLITIQUE,
                'libelle' => 'Accès administratif à la console GAMAD Core',
                'proprietaire_reference' => $acteur,
                'source_reference' => $source,
            ] + $meta('R01-CONSOLE-INSCRIPTION'));
            if (isset($inscription['refus'])) {
                throw new \RuntimeException('Inscription refusée : '.json_encode($inscription));
            }

            $version = '1.0.0';
            $operations = [
                ['creerVersion', [self::POLITIQUE, ['version' => $version] + $meta('R01-CONSOLE-VERSION')]],
                ['ajouterRegle', [self::POLITIQUE, $version, [
                    'effet' => 'PERMET',
                    'action_reference' => self::ACTION,
                    'sujet_reference' => $acteur,
                    'ressource_reference' => null,
                    'motif' => 'Seule l’autorité d’inscription avec mandat actif peut accéder à la console administrative.',
                ] + $meta('R01-CONSOLE-REGLE')]],
                ['soumettreVersion', [self::POLITIQUE, $version, $meta('R01-CONSOLE-SOUMISSION')]],
            ];
            foreach ($operations as [$methode, $arguments]) {
                $resultat = $registre->$methode(...$arguments);
                if (isset($resultat['refus'])) {
                    throw new \RuntimeException($methode.' refusée : '.json_encode($resultat));
                }
            }

            $simulation = $registre->simulerVersion(self::POLITIQUE, $version, [
                'jeu_reference' => 'R01-CONSOLE-SIMULATION',
                'cas' => [
                    ['sujet' => $acteur, 'action' => self::ACTION, 'attendu' => 'PERMIS'],
                    ['sujet' => 'IDN-PER-INCONNUE', 'action' => self::ACTION, 'attendu' => 'REFUSÉ'],
                ],
            ] + $meta('R01-CONSOLE-SIMULATION'));
            if (isset($simulation['refus']) || ($simulation['resultat'] ?? null) !== 'REUSSIE') {
                throw new \RuntimeException('Simulation non réussie : '.json_encode($simulation));
            }

            $activation = $registre->activerVersion(self::POLITIQUE, $version, [
                'motif' => 'R01 : habilitation explicite de l’autorité administrative',
            ] + $meta('R01-CONSOLE-ACTIVATION'));
            if (isset($activation['refus'])) {
                throw new \RuntimeException('Activation refusée : '.json_encode($activation));
            }

            $this->info('R01 : politique dédiée activée ; aucun autre sujet autorisé.');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('R01 interrompu : '.$e->getMessage());
            return self::FAILURE;
        }
    }
}
