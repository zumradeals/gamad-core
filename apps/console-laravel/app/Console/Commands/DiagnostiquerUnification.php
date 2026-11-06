<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Diagnostic sans écriture pour la convergence Réseau + Core.
 * Ne prétend pas prouver l'unicité physique de la base sans connexion effective.
 */
final class DiagnostiquerUnification extends Command
{
    protected $signature = 'gamad:unification:diagnostic';

    protected $description = 'Vérifier les connexions déclarées avant unification GAMAD, sans migration ni écriture';

    public function handle(): int
    {
        $connections = config('database.connections', []);
        $names = array_values(array_filter(array_keys($connections), fn ($name) =>
            $name === config('database.default') || str_starts_with($name, 'gamad_')
        ));

        $this->line('Diagnostic statique des connexions déclarées (aucune connexion réseau effectuée).');
        $targets = [];

        foreach ($names as $name) {
            $connection = $connections[$name];
            $driver = (string) ($connection['driver'] ?? 'inconnu');
            $url = $connection['url'] ?? null;
            $database = (string) ($connection['database'] ?? '');
            $host = (string) ($connection['host'] ?? '');
            $port = (string) ($connection['port'] ?? '');

            // Ne jamais afficher les URL de connexion : elles peuvent contenir des secrets.
            if (is_string($url) && $url !== '') {
                $target = 'URL_CONFIGUREE_NON_INSPECTEE';
            } else {
                $target = implode(':', [$host, $port, $database]);
            }

            $targets[$name] = [$driver, $target];
            $this->line($name.' : '.$driver.' / '.$target);
        }

        $unique = array_unique(array_map(
            fn ($target) => implode('|', $target),
            array_values($targets),
        ));

        if (count($unique) !== 1 || count($targets) === 0
            || in_array('URL_CONFIGUREE_NON_INSPECTEE', array_column($targets, 1), true)) {
            $this->warn('Unicité PostgreSQL non démontrée : ne pas basculer le Réseau.');
            return self::FAILURE;
        }

        if (reset($targets)[0] !== 'pgsql') {
            $this->warn('La cible déclarée commune n’est pas PostgreSQL.');
            return self::FAILURE;
        }

        $this->info('Cible PostgreSQL déclarée identique. Vérifier encore les connexions réelles, schémas et transactions.');
        return self::SUCCESS;
    }
}
