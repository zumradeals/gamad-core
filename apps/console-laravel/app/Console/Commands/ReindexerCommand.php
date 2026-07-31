<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Gamad\RegistreNormes\BaselineOperationnelle;
use Gamad\RegistreNormes\Db;
use Illuminate\Console\Command;

/**
 * Reconstruit l'index technique depuis une baseline opérationnelle versionnée.
 *
 * La commande ne parcourt plus le corpus Genesis II. La source importée est
 * contrôlée par empreinte, validée structurellement et appliquée dans une
 * transaction unique. Une erreur laisse l'index précédent intact.
 */
final class ReindexerCommand extends Command
{
    protected $signature = 'registre:reindexer';

    protected $description = "Reconstruit l'index technique depuis la baseline opérationnelle versionnée.";

    public function handle(): int
    {
        $baseline = BaselineOperationnelle::standard();
        $this->line(sprintf(
            'Baseline : v%d · SHA-256 %s',
            $baseline->version(),
            $baseline->empreinte(),
        ));
        $this->line('Fichier : '.$baseline->chemin());
        $this->newLine();

        try {
            $pdo = Db::connect();
            $this->line('Moteur : '.Db::driver($pdo));
            $resultat = $baseline->reconstruire($pdo);
        } catch (\Throwable $e) {
            $this->error('Réindexation refusée : '.$e->getMessage());
            $this->line("Aucune reconstruction partielle n'a été conservée.");

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Index reconstruit : %d normes, %d versions, %d politiques, %d règles, '
            .'%d identités, %d fonctions et %d mandat(s).',
            $resultat['normes'],
            $resultat['versions'],
            (int) $pdo->query('SELECT count(*) FROM politique')->fetchColumn(),
            $resultat['regles'],
            $resultat['entites'],
            $resultat['fonctions'],
            $resultat['mandats'],
        ));
        $this->line(sprintf(
            'Compatibilité héritée conservée : %d adoptions, %d statuts et %d états de capacité.',
            $resultat['adoptions'],
            $resultat['statuts'],
            $resultat['etats'],
        ));
        $this->newLine();
        $this->info('Réindexation opérationnelle terminée sans lecture du corpus Markdown.');

        return self::SUCCESS;
    }
}
