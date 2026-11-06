<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use JsonException;

final class VerifierCollisionsRoutes extends Command
{
    protected $signature = 'gamad:unification:routes
        {reseau : Chemin du JSON produit par artisan route:list --json dans GAMAD Réseau}
        {core : Chemin du JSON produit par artisan route:list --json dans GAMAD Core}';

    protected $description = 'Détecter les collisions exactes de méthode et URI avant le transfert du Réseau';

    public function handle(): int
    {
        try {
            $reseau = $this->charger((string) $this->argument('reseau'));
            $core = $this->charger((string) $this->argument('core'));
        } catch (\RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $coreRoutes = $this->indexer($core);
        $collisions = [];

        foreach ($reseau as $route) {
            foreach ($this->methodes($route['method']) as $methode) {
                $cle = $methode.' '.trim($route['uri'], '/');
                if (isset($coreRoutes[$cle])) {
                    $collisions[$cle] = true;
                }
            }
        }

        foreach (array_keys($collisions) as $collision) {
            $this->error('Collision : '.$collision);
        }

        if ($collisions !== []) {
            $this->warn('Transfert bloqué : '.count($collisions).' collision(s) exacte(s).');

            return self::FAILURE;
        }

        $this->info('Aucune collision exacte méthode/URI détectée.');
        $this->warn('Les conflits de routes paramétrées, noms, middlewares et autorisations restent à contrôler.');

        return self::SUCCESS;
    }

    /** @return list<array{method: string, uri: string}> */
    private function charger(string $chemin): array
    {
        if (! is_file($chemin) || ! is_readable($chemin)) {
            throw new \RuntimeException('Inventaire de routes inaccessible : '.$chemin);
        }

        try {
            $routes = json_decode((string) file_get_contents($chemin), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new \RuntimeException('JSON de routes invalide : '.$chemin, 0, $exception);
        }

        if (! is_array($routes) || ! array_is_list($routes)) {
            throw new \RuntimeException('Format route:list --json attendu : '.$chemin);
        }

        foreach ($routes as $route) {
            if (! is_array($route) || ! isset($route['method'], $route['uri'])
                || ! is_string($route['method']) || ! is_string($route['uri'])) {
                throw new \RuntimeException('Entrée de route invalide : '.$chemin);
            }
        }

        return $routes;
    }

    /** @param list<array{method: string, uri: string}> $routes
     *  @return array<string, true>
     */
    private function indexer(array $routes): array
    {
        $index = [];

        foreach ($routes as $route) {
            foreach ($this->methodes($route['method']) as $methode) {
                $index[$methode.' '.trim($route['uri'], '/')] = true;
            }
        }

        return $index;
    }

    /** @return list<string> */
    private function methodes(string $methodes): array
    {
        return array_values(array_unique(array_filter(
            array_map('trim', explode('|', strtoupper($methodes))),
            fn (string $methode): bool => $methode !== '',
        )));
    }
}
