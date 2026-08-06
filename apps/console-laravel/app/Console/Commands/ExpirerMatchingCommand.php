<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Gamad\MoteurMatching\Magasin as MatchingMagasin;
use Gamad\MoteurMatching\RegistreMatching;
use Illuminate\Console\Command;

/**
 * Tâche planifiée `matching:expiration` (CAP-CORE-021, doc de chantier 05
 * §6). Purement déclarative : la décision effective (`Activation::verifier`,
 * `Segments::verifierAppartenance`) compare déjà `expire_le` à l'instant
 * réel, indépendamment de la colonne `etat` — cette commande maintient
 * seulement l'état affiché cohérent pour la console, l'API et les futures
 * métriques. Idempotente, sans effet sur des lignes déjà expirées.
 *
 * `matching:purge`, `matching:reconciliation`, `matching:rapport-qualite`,
 * `matching:rapport-equite`, `matching:verifier-preuves` et
 * `matching:diagnostiquer` (doc 05 §6) ne sont pas livrées ici — voir le
 * README du module pour la réserve documentée de chacune, notamment
 * `reconciliation` : l'exécution (`RegistreMatching::executer`) est
 * entièrement transactionnelle dans ce périmètre, un état `EN_COURS` visible
 * par une autre connexion ne peut donc pas se produire structurellement ;
 * construire une réconciliation pour un problème qui ne peut pas survenir
 * serait du code mort, pas une garde utile.
 */
final class ExpirerMatchingCommand extends Command
{
    protected $signature = 'matching:expiration';

    protected $description = 'Marque EXPIRE/EXPIREE les segments et activations dont expire_le est dépassé (CAP-CORE-021).';

    public function handle(): int
    {
        try {
            $registre = new RegistreMatching(MatchingMagasin::connecter());
        } catch (\Throwable $e) {
            $this->error('Expiration interrompue : ' . $e->getMessage());

            return self::FAILURE;
        }

        $segments = $registre->expirerSegmentsEchus();
        $activations = $registre->expirerActivationsEchues();

        $this->info(sprintf(
            '%d segment(s) et %d activation(s) marqué(s) expiré(s).',
            $segments['segments_expires'],
            $activations['activations_expirees'],
        ));

        return self::SUCCESS;
    }
}
