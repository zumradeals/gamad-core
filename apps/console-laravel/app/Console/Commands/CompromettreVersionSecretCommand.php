<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Gamad\RegistreIdentites\PolitiqueInscription;
use Gamad\RegistreSecretsCles\Magasin as SecretsMagasin;
use Gamad\RegistreSecretsCles\PolitiqueSecretsCles;
use Gamad\RegistreSecretsCles\RegistreSecretsCles;
use Illuminate\Console\Command;

/**
 * Déclare une compromission (CAP-CORE-016) — commande d'urgence, disponible
 * mais auditée. Bloque immédiatement la version, sans étape intermédiaire.
 */
final class CompromettreVersionSecretCommand extends Command
{
    protected $signature = 'core:secrets:version-compromettre
        {id : identifiant numérique de la version}
        {--niveau=CONFIRMEE : SUSPECTEE, PROBABLE ou CONFIRMEE}
        {--source= : référence de la source ayant détecté la compromission}
        {--motif= : motif de la déclaration, sans valeur secrète}';

    protected $description = 'Déclare la compromission d’une version de secret — blocage immédiat, aucune valeur.';

    public function handle(): int
    {
        $id = (int) $this->argument('id');
        $niveau = (string) $this->option('niveau');
        $source = trim((string) $this->option('source'));
        $motif = trim((string) $this->option('motif'));
        if ($source === '' || $motif === '') {
            $this->error('--source et --motif sont obligatoires.');

            return self::FAILURE;
        }
        if (!$this->confirm("Confirmer la compromission de la version #{$id} (niveau {$niveau}) ?")) {
            $this->line('Annulé.');

            return self::FAILURE;
        }

        try {
            $registre = new RegistreSecretsCles(SecretsMagasin::connecter());
        } catch (\Throwable $e) {
            $this->error('Commande interrompue : ' . $e->getMessage());

            return self::FAILURE;
        }

        $acteur = PolitiqueInscription::AUTORITE_INSCRIPTION;
        $resultat = $registre->declarerCompromission([
            'secret_version_id' => $id, 'niveau' => $niveau, 'source_reference' => $source, 'motif' => $motif,
            'politique' => PolitiqueSecretsCles::POLITIQUE, 'producteur' => $acteur,
            'preuve' => 'CLI-COMPROMISSION-' . strtoupper(bin2hex(random_bytes(6))),
        ]);
        if (isset($resultat['refus'])) {
            $this->error("Refusé : {$resultat['refus']} ({$resultat['detail']})");

            return self::FAILURE;
        }

        $this->info("Compromission {$resultat['reference']} déclarée. Version #{$id} bloquée.");
        $this->line('Prochaine étape : planifier une rotation d’urgence (core:secrets:rotation-simuler).');

        return self::SUCCESS;
    }
}
