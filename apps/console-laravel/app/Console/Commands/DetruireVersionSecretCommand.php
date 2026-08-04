<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Gamad\RegistreIdentites\PolitiqueInscription;
use Gamad\RegistreSecretsCles\AdaptateurParType;
use Gamad\RegistreSecretsCles\Magasin as SecretsMagasin;
use Gamad\RegistreSecretsCles\PolitiqueSecretsCles;
use Gamad\RegistreSecretsCles\RegistreSecretsCles;
use Illuminate\Console\Command;

/**
 * Destruction gouvernée d'une version de secret (CAP-CORE-016).
 *
 * Refuse toute exécution en `--no-interaction` : une destruction réelle
 * exige toujours une confirmation explicite dans ce chantier, pas seulement
 * un drapeau passé sur la ligne de commande.
 */
final class DetruireVersionSecretCommand extends Command
{
    protected $signature = 'core:secrets:version-detruire {id : identifiant numérique de la version}';

    protected $description = 'Détruit une version de secret non active, sans dépendance bloquante — confirmation renforcée obligatoire.';

    public function handle(): int
    {
        if ($this->option('no-interaction')) {
            $this->error('Une destruction réelle ne peut pas s’exécuter en --no-interaction.');

            return self::FAILURE;
        }
        $id = (int) $this->argument('id');

        try {
            $registre = new RegistreSecretsCles(SecretsMagasin::connecter());
        } catch (\Throwable $e) {
            $this->error('Commande interrompue : ' . $e->getMessage());

            return self::FAILURE;
        }

        $secretReference = null;
        $version = null;
        foreach ($registre->listerSecrets() as $secret) {
            foreach ($registre->listerVersions((string) $secret['reference']) as $v) {
                if ((int) $v['id'] === $id) {
                    $secretReference = (string) $secret['reference'];
                    $version = $v;
                }
            }
        }
        if ($version === null) {
            $this->error("Version #{$id} inconnue.");

            return self::FAILURE;
        }
        $fournisseur = $registre->resoudreFournisseur((string) $version['fournisseur_reference']);
        $adaptateur = $fournisseur !== null ? AdaptateurParType::resoudre((string) $fournisseur['type_fournisseur']) : null;
        if ($adaptateur === null) {
            $this->error('Aucun adaptateur borné disponible pour ce type de fournisseur.');

            return self::FAILURE;
        }

        $this->warn("Destruction irréversible de {$secretReference} version {$version['version']} (#{$id}).");
        if (!$this->confirm('Confirmer la destruction ?', false)) {
            $this->line('Annulé.');

            return self::FAILURE;
        }

        $acteur = PolitiqueInscription::AUTORITE_INSCRIPTION;
        $resultat = $registre->detruireVersion($id, $adaptateur, [
            'confirmation_renforcee' => true,
            'politique' => PolitiqueSecretsCles::POLITIQUE, 'producteur' => $acteur,
            'preuve' => 'CLI-DESTRUCTION-' . strtoupper(bin2hex(random_bytes(6))),
        ]);
        if (isset($resultat['refus'])) {
            $this->error("Refusé : {$resultat['refus']} ({$resultat['detail']})");

            return self::FAILURE;
        }

        $this->info("Version #{$id} détruite.");

        return self::SUCCESS;
    }

}
