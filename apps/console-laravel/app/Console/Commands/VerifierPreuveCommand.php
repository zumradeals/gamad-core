<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Gamad\RegistrePreuves\Magasin as PreuvesMagasin;
use Gamad\RegistrePreuves\RegistrePreuves;
use Illuminate\Console\Command;

/** Vérifie une preuve déjà émise (CAP-CORE-015) — lecture seule, aucune réparation. */
final class VerifierPreuveCommand extends Command
{
    protected $signature = 'core:preuves:verifier
        {reference : référence de la preuve (PRF-GAMAD-…)}
        {--empreinte= : empreinte présentée à comparer}
        {--json : sortie JSON}';

    protected $description = 'Vérifie une preuve déjà émise, sans réparation implicite.';

    public function handle(): int
    {
        try {
            $registre = new RegistrePreuves(PreuvesMagasin::connecter());
        } catch (\Throwable $e) {
            $this->error('Vérification interrompue : ' . $e->getMessage());

            return self::FAILURE;
        }

        $reference = (string) $this->argument('reference');
        if ($registre->resoudrePreuve($reference) === null) {
            $this->error("Preuve `{$reference}` inconnue.");

            return self::FAILURE;
        }
        $dossier = [];
        $empreinte = $this->option('empreinte');
        if (is_string($empreinte) && $empreinte !== '') {
            $dossier['empreinte_presentee'] = $empreinte;
        }
        $resultat = $registre->verifierPreuve($reference, $dossier);

        if ($this->option('json')) {
            $this->line(json_encode($resultat, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } else {
            $this->info("Résultat : {$resultat['resultat']}");
            $this->line('Utilisable : ' . ($resultat['preuve_utilisable'] ? 'oui' : 'non'));
        }

        return $resultat['resultat'] === 'VALIDE' ? self::SUCCESS : self::FAILURE;
    }
}
