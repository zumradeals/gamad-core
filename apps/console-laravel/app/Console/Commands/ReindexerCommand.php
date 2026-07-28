<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Gamad\RegistreNormes\Ctr04;
use Gamad\RegistreNormes\Db;
use Gamad\RegistreNormes\Ingestion;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * Reconstruit l'index dérivé depuis le corpus versionné.
 *
 * Livrée au titre de CAP-CORE-006 (CONCEPTION-CAP-CORE-006-…-0001, Titre IV,
 * Articles 15 et 16, adoptée par ADOPTION-0032). Solde la dette contractée par
 * ADOPTION-0031 : la réindexation de production avait dû être exécutée par un
 * script rédigé hors du dépôt, hors de portée des gardes et hors de la main de
 * l'autorité.
 *
 * Aucune écriture du corpus (INV-4) : le sens demeure unique, des fichiers vers
 * l'index (INV-5). Aucun secret en argument : la connexion procède de
 * DATABASE_URL, comme le reste du service.
 */
final class ReindexerCommand extends Command
{
    protected $signature = 'registre:reindexer';

    protected $description = "Reconstruit l'index dérivé depuis le corpus, après vérification des deux gardes.";

    public function handle(): int
    {
        $corpus = $this->corpus();

        $this->line("Corpus : {$corpus}");
        $this->newLine();

        // Article 16 : un index reconstruit depuis un corpus incohérent
        // propagerait l'incohérence. La commande refuse alors de s'exécuter.
        if (!$this->gardesVertes($corpus)) {
            $this->newLine();
            $this->error('Réindexation REFUSÉE : les deux gardes ne sont pas vertes.');
            $this->line("Corriger le corpus, puis relancer. Aucun index n'a été touché.");

            return self::FAILURE;
        }

        $this->newLine();

        try {
            $pdo = Db::connect();
            $this->line('Moteur : ' . Db::driver($pdo));

            $r = (new Ingestion($pdo, $corpus))->executer();
            $this->info(sprintf(
                'Index reconstruit : %d adoptions, %d normes, %d versions, %d statuts.',
                $r['adoptions'],
                $r['normes'],
                $r['versions'],
                $r['statuts'],
            ));

            $index = (new Ctr04($pdo, $corpus))->resoudreIndex();
        } catch (\Throwable $e) {
            $this->error('Échec de la reconstruction : ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->line(sprintf(
            'Cohérence : %d actes primaires, %d en index.',
            $index['actes_primaires'],
            $index['index'],
        ));

        if ($index['divergences'] !== []) {
            $this->newLine();
            $this->error(sprintf('%d divergence(s) d\'index :', count($index['divergences'])));
            foreach ($index['divergences'] as $d) {
                $this->line("  · {$d}");
            }

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Aucune divergence. Index conforme au corpus.');

        return self::SUCCESS;
    }

    /**
     * Localise la racine du corpus selon la même convention que le cœur adopté
     * (`core/registre-normes/bootstrap.php`) : `CORPUS_PATH` s'il est fourni,
     * sinon deux niveaux au-dessus de l'application. Une convention unique,
     * non deux — et la commande en devient éprouvable contre un corpus de test.
     */
    private function corpus(): string
    {
        $corpus = getenv('CORPUS_PATH');

        return (is_string($corpus) && $corpus !== '') ? $corpus : dirname(base_path(), 2);
    }

    /**
     * Exécute les deux gardes du dépôt, séparément et sans les réécrire
     * (ADOPTION-0027, Art. 4). Le contrôle documentaire Python et le test de
     * comportement PHP demeurent deux programmes distincts ; cette commande les
     * invoque, elle ne les absorbe pas.
     */
    private function gardesVertes(string $corpus): bool
    {
        $gardes = [
            'Garde 1 — intégrité documentaire' => ['python3', 'outils/verifier-integrite.py'],
            'Garde 2 — preuve P3'              => ['php', 'core/registre-normes/tests/temporel_p3.php'],
        ];

        $toutes = true;

        foreach ($gardes as $libelle => $commande) {
            $process = new Process($commande, $corpus, timeout: 300);
            $process->run();

            if ($process->isSuccessful()) {
                $this->line("  <info>[OK]</info>    {$libelle}");
                continue;
            }

            $toutes = false;
            $this->line("  <error>[ÉCHEC]</error> {$libelle} (code " . $process->getExitCode() . ')');
            foreach (preg_split('/\R/', trim($process->getErrorOutput() ?: $process->getOutput())) ?: [] as $ligne) {
                if ($ligne !== '') {
                    $this->line("          {$ligne}");
                }
            }
        }

        return $toutes;
    }
}
