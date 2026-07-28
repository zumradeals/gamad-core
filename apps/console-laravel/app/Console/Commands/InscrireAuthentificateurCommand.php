<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Gamad\RegistreAcces\Ctr16;
use Gamad\RegistreAcces\Magasin;
use Gamad\RegistreIdentites\Ctr01;
use Gamad\RegistreNormes\Db;
use Illuminate\Console\Command;

/**
 * Inscrit un authentificateur pour une entité du Registre des identités.
 *
 * CETTE COMMANDE EST DESTINÉE À ÊTRE EXÉCUTÉE PAR L'AUTORITÉ ELLE-MÊME.
 *
 * Le secret est lu en saisie masquée, n'est jamais affiché, jamais journalisé,
 * jamais passé en argument de ligne de commande — donc jamais présent dans
 * l'historique du shell, ni dans la liste des processus. Seule son empreinte
 * non réversible est conservée (INV-24).
 *
 * L'agent qui a écrit cette commande n'a aucun moyen d'en connaître le secret :
 * c'est la limite 4 de ADOPTION-0037, qu'aucune instruction ne lève.
 */
final class InscrireAuthentificateurCommand extends Command
{
    protected $signature = 'identite:authentifier {entite : référence de l\'entité, ex. AUT-GAMAD-001}';

    protected $description = "Inscrit un authentificateur pour une entité (secret saisi par l'autorité, jamais par l'agent).";

    public function handle(): int
    {
        $entite = (string) $this->argument('entite');

        // L'entité doit exister au Registre des identités : un compte n'est
        // pas une identité, il s'y rattache (INV-23).
        try {
            $connue = (new Ctr01(Db::connect()))->resoudreIdentite($entite);
        } catch (\Throwable $e) {
            $this->error("Index dérivé indisponible : {$e->getMessage()}");
            $this->line('Lancer d\'abord : php artisan registre:reindexer');

            return self::FAILURE;
        }

        if ($connue === null) {
            $this->error("Entité inconnue du Registre des identités : {$entite}");
            $this->line("Un authentificateur se rattache à une identité inscrite ; il ne la crée pas (INV-23).");

            return self::FAILURE;
        }

        $this->line("Entité : <info>{$connue['libelle']}</info> ({$connue['type']})");
        $this->newLine();
        $this->line('Le secret ne sera pas affiché, pas journalisé, et ne quittera pas cette machine.');
        $this->line('Seule son empreinte non réversible sera conservée.');
        $this->newLine();

        $secret = (string) $this->secret('Secret (douze caractères au moins)');
        $confirmation = (string) $this->secret('Confirmer le secret');

        if ($secret !== $confirmation) {
            $this->error('Les deux saisies diffèrent. Aucun authentificateur inscrit.');

            return self::FAILURE;
        }

        try {
            $reference = (new Ctr16(Magasin::connecter()))->inscrireAuthentificateur($entite, $secret);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } finally {
            // Le secret ne survit pas à la portée de cette méthode.
            $secret = $confirmation = '';
            unset($secret, $confirmation);
        }

        $this->newLine();
        $this->info("Authentificateur inscrit : {$reference}");
        $this->line("  entité    : {$entite}");
        $this->line('  assurance : AS1 — FACTEUR UNIQUE');
        $this->newLine();
        $this->warn('Facteur unique : les Articles 78 et 79 de SECURITY-GOVERNANCE-0001 exigent');
        $this->warn('plusieurs facteurs pour un compte privilégié. Cet écart est déclaré et');
        $this->warn("demeure à trancher par l'autorité.");

        return self::SUCCESS;
    }
}
