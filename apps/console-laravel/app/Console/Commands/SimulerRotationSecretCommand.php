<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Gamad\RegistreIdentites\PolitiqueInscription;
use Gamad\RegistreSecretsCles\Magasin as SecretsMagasin;
use Gamad\RegistreSecretsCles\PolitiqueSecretsCles;
use Gamad\RegistreSecretsCles\RegistreSecretsCles;
use Illuminate\Console\Command;

/**
 * Planifie un plan de rotation en BROUILLON (CAP-CORE-016) — n'exécute rien.
 * `--consommateurs` inventorie l'impact ; sans lui, la planification est
 * refusée par le registre (aucun plan sans consommateurs impactés).
 */
final class SimulerRotationSecretCommand extends Command
{
    protected $signature = 'core:secrets:rotation-simuler
        {secret : référence du secret}
        {--strategie=DOUBLE_LECTURE_ECRITURE_NOUVELLE}
        {--consommateurs=* : références des consommateurs impactés}
        {--retour-arriere=1 : 1 si un retour arrière est possible, sinon 0}';

    protected $description = 'Planifie un plan de rotation en BROUILLON, sans exécuter aucune étape.';

    public function handle(): int
    {
        $consommateurs = (array) $this->option('consommateurs');
        if ($consommateurs === []) {
            $this->error('--consommateurs est obligatoire (au moins une référence impactée).');

            return self::FAILURE;
        }

        try {
            $registre = new RegistreSecretsCles(SecretsMagasin::connecter());
        } catch (\Throwable $e) {
            $this->error('Commande interrompue : ' . $e->getMessage());

            return self::FAILURE;
        }

        $acteur = PolitiqueInscription::AUTORITE_INSCRIPTION;
        $resultat = $registre->planifierRotation([
            'secret_reference' => (string) $this->argument('secret'),
            'strategie' => (string) $this->option('strategie'),
            'date_prevue' => gmdate('c'),
            'retour_arriere_autorise' => (bool) (int) $this->option('retour-arriere'),
            'impact' => ['consommateurs' => $consommateurs],
            'politique' => PolitiqueSecretsCles::POLITIQUE, 'producteur' => $acteur,
            'preuve' => 'CLI-ROTATION-PLAN-' . strtoupper(bin2hex(random_bytes(6))),
        ]);
        if (isset($resultat['refus'])) {
            $this->error("Refusé : {$resultat['refus']} ({$resultat['detail']})");

            return self::FAILURE;
        }

        $this->info("Plan {$resultat['reference']} créé en BROUILLON.");
        $this->line("Valider : php artisan tinker, ou POST /api/v1/rotations-secrets/{$resultat['reference']}/validation.");

        return self::SUCCESS;
    }
}
