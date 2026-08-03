<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\ImportateurSqlite;
use Gamad\RegistreAcces\Magasin as AccesMagasin;
use Gamad\RegistreContrats\Magasin as ContratsMagasin;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistrePolitiques\Magasin as PolitiquesMagasin;
use Gamad\RegistreProduits\Magasin as ProduitsMagasin;
use Gamad\RegistreRealms\Magasin as RealmsMagasin;
use Gamad\RegistreSources\Magasin as SourcesMagasin;
use Gamad\RegistreVocabulaire\Magasin as VocabulaireMagasin;
use Illuminate\Console\Command;

final class ImporterSqliteCommand extends Command
{
    protected $signature = 'core:fondation:importer-sqlite
        {--acces= : chemin absolu du magasin SQLite d’accès}
        {--identites= : chemin absolu du registre SQLite d’identités}
        {--produits= : chemin absolu du registre SQLite des produits}
        {--sources= : chemin absolu du registre SQLite des sources}
        {--politiques= : chemin absolu du registre SQLite des politiques}
        {--contrats= : chemin absolu du registre SQLite des contrats}
        {--vocabulaire= : chemin absolu du registre SQLite du vocabulaire}
        {--realms= : chemin absolu du registre SQLite des realms}
        {--force : confirme l’import vers les cibles configurées}';

    protected $description = 'Importe les magasins SQLite dans des cibles PostgreSQL vides.';

    public function handle(ImportateurSqlite $importateur): int
    {
        if (!$this->option('force')) {
            $this->error('Import refusé sans --force. Les cibles doivent être vides.');

            return self::FAILURE;
        }
        foreach ([
            'MAGASIN_URL', 'IDENTITY_REGISTRY_URL', 'PRODUCT_REGISTRY_URL',
            'SOURCE_REGISTRY_URL', 'POLICY_REGISTRY_URL', 'CONTRACT_REGISTRY_URL',
            'VOCABULARY_REGISTRY_URL', 'REALM_REGISTRY_URL',
        ] as $variable) {
            if (trim((string) getenv($variable)) === '') {
                $this->error("{$variable} doit désigner la cible PostgreSQL.");

                return self::FAILURE;
            }
        }

        $acces = (string) ($this->option('acces') ?: getenv('MAGASIN_PATH'));
        $identites = (string) ($this->option('identites') ?: getenv('IDENTITY_REGISTRY_PATH'));
        $produits = (string) ($this->option('produits') ?: getenv('PRODUCT_REGISTRY_PATH'));
        $sources = (string) ($this->option('sources') ?: getenv('SOURCE_REGISTRY_PATH'));
        $politiques = (string) ($this->option('politiques') ?: getenv('POLICY_REGISTRY_PATH'));
        $contrats = (string) ($this->option('contrats') ?: getenv('CONTRACT_REGISTRY_PATH'));
        $vocabulaire = (string) ($this->option('vocabulaire') ?: getenv('VOCABULARY_REGISTRY_PATH'));
        $realms = (string) ($this->option('realms') ?: getenv('REALM_REGISTRY_PATH'));
        if (!$this->absolu($acces) || !$this->absolu($identites) || !$this->absolu($produits)
            || !$this->absolu($sources) || !$this->absolu($politiques) || !$this->absolu($contrats)
            || !$this->absolu($vocabulaire) || !$this->absolu($realms)) {
            $this->error('Les huit chemins SQLite sources doivent être absolus.');

            return self::FAILURE;
        }

        try {
            $cibleAcces = AccesMagasin::connecter();
            $cibleIdentites = IdentiteMagasin::connecter();
            $cibleProduits = ProduitsMagasin::connecter();
            $cibleSources = SourcesMagasin::connecter();
            $ciblePolitiques = PolitiquesMagasin::connecter();
            $cibleContrats = ContratsMagasin::connecter();
            $cibleVocabulaire = VocabulaireMagasin::connecter();
            $cibleRealms = RealmsMagasin::connecter();
            foreach ([$cibleAcces, $cibleIdentites, $cibleProduits, $cibleSources, $ciblePolitiques, $cibleContrats, $cibleVocabulaire, $cibleRealms] as $pdo) {
                if ($pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) !== 'pgsql') {
                    throw new \RuntimeException('Une cible configurée n’utilise pas PostgreSQL.');
                }
            }

            $resultats = [
                'accès' => $importateur->importerAcces($acces, $cibleAcces),
                'identités' => $importateur->importerIdentites($identites, $cibleIdentites),
                'produits' => $importateur->importerProduits($produits, $cibleProduits),
                'sources' => $importateur->importerSources($sources, $cibleSources),
                'politiques' => $importateur->importerPolitiques($politiques, $ciblePolitiques),
                'contrats' => $importateur->importerContrats($contrats, $cibleContrats),
                'vocabulaire' => $importateur->importerVocabulaire($vocabulaire, $cibleVocabulaire),
                'realms' => $importateur->importerRealms($realms, $cibleRealms),
            ];
        } catch (\Throwable $e) {
            $this->error('Import interrompu : ' . $e->getMessage());

            return self::FAILURE;
        }

        foreach ($resultats as $registre => $tables) {
            $this->info($registre);
            foreach ($tables as $table => $nombre) {
                $this->line("  {$table}: {$nombre}");
            }
        }
        $this->warn(
            'Les sources SQLite sont conservées. Ne les archiver qu’après readiness, sauvegarde PostgreSQL et exercice de restauration.',
        );

        return self::SUCCESS;
    }

    private function absolu(string $chemin): bool
    {
        return $chemin !== '' && str_starts_with($chemin, '/');
    }
}
