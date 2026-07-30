<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Gamad\JournalOperationnel\Magasin as JournalMagasin;
use Gamad\RegistreAcces\Magasin as AccesMagasin;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreNormes\Db;
use Illuminate\Console\Command;

final class MigrerFondationCommand extends Command
{
    protected $signature = 'core:fondation:migrer {--force : autorise l’exécution en production}';

    protected $description = 'Applique les migrations additives des magasins accès, identités et journal.';

    public function handle(): int
    {
        if (app()->environment('production') && !$this->option('force')) {
            $this->error('Utiliser --force pour confirmer une migration de production.');

            return self::FAILURE;
        }

        if (app()->environment('production')) {
            foreach ([
                'DATABASE_URL',
                'MAGASIN_URL',
                'IDENTITY_REGISTRY_URL',
                'JOURNAL_OPERATIONNEL_URL',
            ] as $variable) {
                if (trim((string) getenv($variable)) === '') {
                    $this->error("{$variable} est obligatoire en production.");

                    return self::FAILURE;
                }
            }
        }

        try {
            $cibles = [
                'index reconstructible' => Db::connect(),
                'accès/authentification' => AccesMagasin::connecter(),
                'identités persistantes' => IdentiteMagasin::connecter(),
                'journal opérationnel' => JournalMagasin::connecter(),
            ];
        } catch (\Throwable $e) {
            $this->error('Migration interrompue : ' . $e->getMessage());

            return self::FAILURE;
        }

        foreach ($cibles as $nom => $pdo) {
            $moteur = (string) $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
            if (app()->environment('production') && $moteur !== 'pgsql') {
                $this->error("{$nom} utilise {$moteur}; PostgreSQL est obligatoire.");

                return self::FAILURE;
            }
            $this->info("[OK] {$nom} — {$moteur}");
        }

        $this->line('Exécuter séparément `php artisan migrate --force` pour les tables Laravel.');
        $this->line('L’index documentaire se reconstruit avec `php artisan registre:reindexer`.');

        return self::SUCCESS;
    }
}
