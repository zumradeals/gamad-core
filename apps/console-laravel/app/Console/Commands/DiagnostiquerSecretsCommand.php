<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Gamad\RegistreSecretsCles\Magasin as SecretsMagasin;
use Gamad\RegistreSecretsCles\RegistreSecretsCles;
use Illuminate\Console\Command;

/**
 * Diagnostic en lecture seule du registre des secrets et clés (CAP-CORE-016).
 *
 * Ne répare rien, ne lit aucune valeur, ne modifie aucun état.
 */
final class DiagnostiquerSecretsCommand extends Command
{
    protected $signature = 'core:secrets:diagnostiquer';

    protected $description = 'Rapporte l’état du registre des secrets et clés, sans réparation implicite.';

    public function handle(): int
    {
        try {
            $registre = new RegistreSecretsCles(SecretsMagasin::connecter());
        } catch (\Throwable $e) {
            $this->error('Diagnostic interrompu : ' . $e->getMessage());

            return self::FAILURE;
        }

        $diagnostic = $registre->diagnostiquerRegistre();
        $this->info("Ressources : {$diagnostic['ressources']}");
        $this->info("Versions actives en écriture : {$diagnostic['versions_actives_ecriture']}");
        $this->info("Doublons d'écriture active : {$diagnostic['doublons_ecriture']}");
        $this->info("Versions compromises actives : {$diagnostic['versions_compromises_actives']}");
        $this->info("Compromissions ouvertes : {$diagnostic['compromissions_ouvertes']}");
        $this->info("Fournisseurs dégradés/suspendus : {$diagnostic['fournisseurs_degrades']}");
        $this->info("Références encore sur fournisseur de transition : {$diagnostic['references_transition']}");

        $fournisseurs = $registre->diagnostiquerFournisseurs();
        foreach ($fournisseurs['fournisseurs'] as $f) {
            $this->line("  {$f['reference']} ({$f['type_fournisseur']}) : {$f['etat']}");
        }

        if (!$diagnostic['coherent']) {
            $this->error('Registre INCOHÉRENT : un doublon de version active en écriture a été détecté.');

            return self::FAILURE;
        }

        $this->info('Registre cohérent.');

        return self::SUCCESS;
    }
}
