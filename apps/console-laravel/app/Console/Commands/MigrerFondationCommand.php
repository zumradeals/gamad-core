<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Gamad\JournalOperationnel\Magasin as JournalMagasin;
use Gamad\RegistreAcces\Magasin as AccesMagasin;
use Gamad\RegistreFederation\SchemaFederation;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreNormes\Db;
use Gamad\RegistreProduits\Magasin as ProduitsMagasin;
use Gamad\RegistreSources\Magasin as SourcesMagasin;
use Illuminate\Console\Command;

final class MigrerFondationCommand extends Command
{
    protected $signature = 'core:fondation:migrer {--force : autorise l’exécution en production}';

    protected $description = 'Applique les migrations additives des magasins accès, identités, produits, sources et journal.';

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
                'PRODUCT_REGISTRY_URL',
                'SOURCE_REGISTRY_URL',
            ] as $variable) {
                if (trim((string) $this->environnement($variable)) === '') {
                    $this->error("{$variable} est obligatoire en production.");

                    return self::FAILURE;
                }
            }
        }

        try {
            $acces = AccesMagasin::connecter();
            // Les jetons fédérés vivent dans le magasin d'accès : leur validité
            // dépend en permanence de la session Core qui les a produits.
            SchemaFederation::migrer($acces);
            $cibles = [
                'index reconstructible' => Db::connect(),
                'accès/authentification/fédération' => $acces,
                'identités persistantes' => IdentiteMagasin::connecter(),
                'produits persistants' => ProduitsMagasin::connecter(),
                'sources persistantes' => SourcesMagasin::connecter(),
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

    /**
     * Laravel ne charge PAS `.env` lorsque la configuration est en cache : le
     * bootstrapper s'arrête net si `bootstrap/cache/config.php` existe. Un
     * `getenv()` seul rendait donc la garde de production faussement bloquante
     * après le premier `php artisan optimize` — la commande refusait de migrer
     * en annonçant une variable absente, alors que les quatre connexions
     * fonctionnaient parfaitement depuis la configuration mise en cache.
     *
     * La garde consulte donc les mêmes sources que les magasins eux-mêmes.
     */
    private function environnement(string $nom): ?string
    {
        $configuration = match ($nom) {
            'DATABASE_URL' => 'database.connections.gamad_index.url',
            'MAGASIN_URL' => 'database.connections.gamad_access.url',
            'IDENTITY_REGISTRY_URL' => 'database.connections.gamad_identity.url',
            'JOURNAL_OPERATIONNEL_URL' => 'database.connections.gamad_journal.url',
            'PRODUCT_REGISTRY_URL' => 'database.connections.gamad_products.url',
            'SOURCE_REGISTRY_URL' => 'database.connections.gamad_sources.url',
            default => null,
        };

        foreach ([
            getenv($nom),
            $configuration === null ? null : config($configuration),
            $_ENV[$nom] ?? null,
            $_SERVER[$nom] ?? null,
        ] as $valeur) {
            if (is_string($valeur) && trim($valeur) !== '') {
                return $valeur;
            }
        }

        return null;
    }
}
