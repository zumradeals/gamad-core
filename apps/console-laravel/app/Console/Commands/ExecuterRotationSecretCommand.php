<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Gamad\RegistreIdentites\PolitiqueInscription;
use Gamad\RegistreSecretsCles\Magasin as SecretsMagasin;
use Gamad\RegistreSecretsCles\PolitiqueSecretsCles;
use Gamad\RegistreSecretsCles\RegistreSecretsCles;
use Illuminate\Console\Command;

/**
 * Exécute une étape d'un plan de rotation VALIDE ou EN_COURS (CAP-CORE-016).
 *
 * Idempotente : rejouer la même étape déjà réussie ne la rejoue pas. Le plan
 * doit avoir été validé au préalable (core:secrets:rotation-simuler puis
 * validation explicite) — cette commande n'exécute jamais un plan encore en
 * BROUILLON.
 */
final class ExecuterRotationSecretCommand extends Command
{
    protected $signature = 'core:secrets:rotation-executer
        {plan : référence du plan}
        {etape : référence de l’étape}
        {--echec : marque cette exécution comme un échec, pour éprouver la reprise}';

    protected $description = 'Exécute une étape d’un plan de rotation validé — idempotente.';

    public function handle(): int
    {
        try {
            $registre = new RegistreSecretsCles(SecretsMagasin::connecter());
        } catch (\Throwable $e) {
            $this->error('Commande interrompue : ' . $e->getMessage());

            return self::FAILURE;
        }

        $acteur = PolitiqueInscription::AUTORITE_INSCRIPTION;
        $resultat = $registre->executerEtapeRotation((string) $this->argument('plan'), (string) $this->argument('etape'), [
            'reussie' => !$this->option('echec'),
            'politique' => PolitiqueSecretsCles::POLITIQUE, 'producteur' => $acteur,
            'preuve' => 'CLI-ROTATION-EXEC-' . strtoupper(bin2hex(random_bytes(6))),
        ]);
        if (isset($resultat['refus'])) {
            $this->error("Refusé : {$resultat['refus']} ({$resultat['detail']})");

            return self::FAILURE;
        }

        $etat = (string) $resultat['etat'];
        $idempotent = ($resultat['idempotent'] ?? false) === true;
        $this->info("Étape {$this->argument('etape')} : {$etat}" . ($idempotent ? ' (déjà réussie, non rejouée)' : ''));

        return $etat === 'ECHOUEE' ? self::FAILURE : self::SUCCESS;
    }
}
