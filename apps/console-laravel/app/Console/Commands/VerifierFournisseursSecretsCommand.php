<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Gamad\RegistreSecretsCles\FournisseurCredentialSystemd;
use Gamad\RegistreSecretsCles\FournisseurEnvironnementTransition;
use Gamad\RegistreSecretsCles\FournisseurFichier0600;
use Gamad\RegistreSecretsCles\FournisseurSecret;
use Gamad\RegistreSecretsCles\Magasin as SecretsMagasin;
use Gamad\RegistreSecretsCles\RegistreSecretsCles;
use Illuminate\Console\Command;

/**
 * Vérifie la disponibilité générique de chaque fournisseur déclaré
 * (CAP-CORE-016). Ne lit ni n'affiche aucune valeur ; une vérification
 * spécifique à une version (avec son handle réel) reste du ressort de
 * `verifierVersion()`, appelée avant chaque activation.
 */
final class VerifierFournisseursSecretsCommand extends Command
{
    protected $signature = 'core:secrets:fournisseurs-verifier';

    protected $description = 'Vérifie la disponibilité générique de chaque fournisseur de secrets déclaré.';

    public function handle(): int
    {
        try {
            $registre = new RegistreSecretsCles(SecretsMagasin::connecter());
        } catch (\Throwable $e) {
            $this->error('Vérification interrompue : ' . $e->getMessage());

            return self::FAILURE;
        }

        $echecs = 0;
        foreach ($registre->listerFournisseurs() as $fournisseur) {
            $adaptateur = $this->adaptateurPour((string) $fournisseur['type_fournisseur']);
            if ($adaptateur === null) {
                $this->line("{$fournisseur['reference']} : type {$fournisseur['type_fournisseur']} sans adaptateur borné dans ce chantier — ignoré.");
                continue;
            }
            $resultat = $registre->verifierFournisseur((string) $fournisseur['reference'], $adaptateur);
            $ligne = "{$fournisseur['reference']} : {$resultat['etat']}";
            if (($resultat['motif'] ?? null) !== null) {
                $ligne .= " ({$resultat['motif']})";
            }
            if ($resultat['etat'] === 'ACTIF') {
                $this->info($ligne);
            } else {
                $this->line($ligne);
                $echecs++;
            }
        }

        $this->info("Fournisseurs vérifiés. {$echecs} non actif(s) après vérification (attendu tant qu'aucune activation n'a eu lieu).");

        return self::SUCCESS;
    }

    private function adaptateurPour(string $type): ?FournisseurSecret
    {
        return match ($type) {
            'FICHIER_0600' => new FournisseurFichier0600('', 0),
            'CREDENTIAL_SYSTEMD' => new FournisseurCredentialSystemd(),
            'VARIABLE_ENVIRONNEMENT_TRANSITION' => new FournisseurEnvironnementTransition(),
            default => null,
        };
    }
}
