<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Garde-fou statique : aucune migration, connexion ou modification de données.
 * Vérifie la forme des clés dans les migrations initiales, pas l'état réel d'une base.
 */
final class VerifierIdentitesReseau extends Command
{
    protected $signature = 'gamad:unification:identites {reseau-migration : Chemin de la migration initiale users du Réseau}';

    protected $description = 'Comparer les clés users/sessions du Réseau et du Core avant toute fusion de schémas';

    public function handle(): int
    {
        $reseau = (string) $this->argument('reseau-migration');
        $core = database_path('migrations/0001_01_01_000000_create_users_table.php');

        if (! is_file($reseau) || ! is_readable($reseau) || ! is_file($core) || ! is_readable($core)) {
            $this->error('Migrations initiales absentes ou illisibles : comparaison impossible.');
            return self::FAILURE;
        }

        $reseauSource = file_get_contents($reseau);
        $coreSource = file_get_contents($core);
        if ($reseauSource === false || $coreSource === false) {
            $this->error('Lecture des migrations impossible.');
            return self::FAILURE;
        }

        $reseauKeys = self::keys($reseauSource);
        $coreKeys = self::keys($coreSource);

        foreach (['users.id', 'sessions.user_id'] as $key) {
            $this->line($key.' : Réseau='.($reseauKeys[$key] ?? 'inconnu')
                .' / Core='.($coreKeys[$key] ?? 'inconnu'));
        }

        if (in_array('inconnu', array_merge(array_values($reseauKeys), array_values($coreKeys)), true)
            || $reseauKeys !== $coreKeys) {
            $this->warn('Schémas d’identité incompatibles ou non démontrés : aucune copie de users/sessions ni migration automatique.');
            return self::FAILURE;
        }

        $this->info('Forme des clés compatible dans les fichiers inspectés ; unicité des identités et données réelles NON démontrée.');
        return self::SUCCESS;
    }

    /** @return array<string, string> */
    private static function keys(string $source): array
    {
        $users = self::tableBody($source, 'users');
        $sessions = self::tableBody($source, 'sessions');

        return [
            'users.id' => preg_match('/\\$table->uuid\\(\\s*[\'"]id[\'"]\\s*\\)->primary\\(\\)/', $users) ? 'uuid'
                : (preg_match('/\\$table->id\\(\\s*\\)/', $users) ? 'bigint' : 'inconnu'),
            'sessions.user_id' => preg_match('/\\$table->foreignUuid\\(\\s*[\'"]user_id[\'"]\\s*\\)/', $sessions) ? 'uuid'
                : (preg_match('/\\$table->foreignId\\(\\s*[\'"]user_id[\'"]\\s*\\)/', $sessions) ? 'bigint' : 'inconnu'),
        ];
    }

    private static function tableBody(string $source, string $table): string
    {
        if (preg_match('/Schema::create\\(\\s*[\'"]'.preg_quote($table, '/').'[\'"]\\s*,\\s*function\\s*\\([^)]*\\)\\s*\\{(.*?)\\}\\s*\\);/s', $source, $matches)) {
            return $matches[1];
        }

        return '';
    }
}
