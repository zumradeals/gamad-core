<?php

declare(strict_types=1);

namespace App\Support;

use Gamad\JournalOperationnel\Magasin as JournalMagasin;
use Gamad\RegistreAcces\Magasin as AccesMagasin;
use Gamad\RegistreContrats\Magasin as ContratsMagasin;
use Gamad\RegistreIdentites\Magasin as IdentiteMagasin;
use Gamad\RegistreNormes\Db;
use Gamad\RegistrePolitiques\Magasin as PolitiquesMagasin;
use Gamad\RegistreProduits\Magasin as ProduitsMagasin;
use Gamad\RegistreSources\Magasin as SourcesMagasin;

/**
 * Readiness sans migration implicite et sans restitution de secrets.
 */
final class EtatFondation
{
    /** @return array<string,mixed> */
    public function inspecter(): array
    {
        $production = app()->environment('production');
        $tablesAcces = [
            'migration_registre_acces',
            'authentificateur',
            'passkey',
            'autorisation_enrolement_passkey',
            'ceremonie_passkey',
            'session_ouverte',
            'migration_registre_federation',
            'jeton_federe',
        ];
        if ($production || config('session.driver') === 'database') {
            $tablesAcces[] = 'migrations';
            $tablesAcces[] = 'sessions';
        }
        $cibles = [
            'index' => $this->inspecterCible(
                'DATABASE_URL',
                fn (): \PDO => Db::connect(),
                ['entite', 'mandat'],
                $production,
            ),
            'acces' => $this->inspecterCible(
                'MAGASIN_URL',
                fn (): \PDO => AccesMagasin::ouvrir(),
                $tablesAcces,
                $production,
            ),
            'identites' => $this->inspecterCible(
                'IDENTITY_REGISTRY_URL',
                fn (): \PDO => IdentiteMagasin::ouvrir(),
                ['migration_registre_identites', 'identite_inscrite', 'evenement_cycle_identite'],
                $production,
            ),
            'produits' => $this->inspecterCible(
                'PRODUCT_REGISTRY_URL',
                fn (): \PDO => ProduitsMagasin::ouvrir(),
                ['migration_registre_produits', 'produit', 'produit_cycle', 'produit_environnement'],
                $production,
            ),
            'sources' => $this->inspecterCible(
                'SOURCE_REGISTRY_URL',
                fn (): \PDO => SourcesMagasin::ouvrir(),
                [
                    'migration_registre_sources', 'source', 'source_cycle', 'source_revision',
                    'source_verification', 'source_finalite', 'source_lignee',
                ],
                $production,
            ),
            'politiques' => $this->inspecterCible(
                'POLICY_REGISTRY_URL',
                fn (): \PDO => PolitiquesMagasin::ouvrir(),
                [
                    'migration_registre_politiques', 'politique', 'politique_version',
                    'regle_politique', 'politique_version_cycle', 'politique_simulation',
                ],
                $production,
            ),
            'contrats' => $this->inspecterCible(
                'CONTRACT_REGISTRY_URL',
                fn (): \PDO => ContratsMagasin::ouvrir(),
                [
                    'migration_registre_contrats', 'contrat', 'contrat_version', 'contrat_version_cycle',
                    'contrat_partie', 'contrat_operation', 'contrat_schema', 'contrat_erreur',
                    'contrat_obligation', 'contrat_compatibilite', 'contrat_conformite', 'contrat_projection',
                ],
                $production,
            ),
            'journal' => $this->inspecterCible(
                'JOURNAL_OPERATIONNEL_URL',
                fn (): \PDO => JournalMagasin::ouvrir(),
                ['migration_journal_operationnel', 'evenement_operationnel'],
                $production,
                verifierJournal: true,
            ),
        ];

        $configuration = [
            'app_debug_desactive' => ! $production || config('app.debug') === false,
            'connexion_laravel_separee' => ! $production
                || config('database.default') === 'gamad_access',
            'sessions_persistantes' => ! $production || config('session.driver') === 'database',
            'sessions_chiffrees' => ! $production || config('session.encrypt') === true,
            'cookies_session_securises' => ! $production || config('session.secure') === true,
            'url_publique_https' => ! $production
                || str_starts_with((string) config('app.url'), 'https://'),
            'passkeys_origine_fermee' => ! $production
                || $this->configurationPasskeysFermee(),
        ];

        $pret = ! in_array(false, array_column($cibles, 'prete'), true)
            && ! in_array(false, $configuration, true);

        return [
            'statut' => $pret ? 'PRET' : 'NON_PRET',
            'pret' => $pret,
            'environnement' => app()->environment(),
            'cibles' => $cibles,
            'configuration' => $configuration,
            'verifie_le' => gmdate('c'),
        ];
    }

    /**
     * @param  callable():\PDO  $ouvrir
     * @param  list<string>  $tables
     * @return array<string,mixed>
     */
    private function inspecterCible(
        string $variable,
        callable $ouvrir,
        array $tables,
        bool $production,
        bool $verifierJournal = false,
    ): array {
        $urlPresente = trim((string) $this->environnement($variable)) !== '';
        if ($production && ! $urlPresente) {
            return [
                'prete' => false,
                'moteur' => null,
                'tables' => false,
                'motif' => "configuration {$variable} absente",
            ];
        }

        try {
            $pdo = $ouvrir();
            $moteur = (string) $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
            $tablesOk = true;
            foreach ($tables as $table) {
                try {
                    $pdo->query("SELECT 1 FROM {$table} LIMIT 1");
                } catch (\Throwable) {
                    $tablesOk = false;
                    break;
                }
            }
            $integrite = null;
            if ($verifierJournal && $tablesOk) {
                $tete = $pdo->query(
                    'SELECT empreinte FROM evenement_operationnel ORDER BY sequence_id DESC LIMIT 1'
                )->fetchColumn();
                $integrite = [
                    'tete_lisible' => $tete === false
                        || preg_match('/^[0-9a-f]{64}$/', (string) $tete) === 1,
                    'verification_complete' => 'php artisan core:journal:verifier',
                ];
            }
            $moteurOk = ! $production || $moteur === 'pgsql';
            $integriteOk = $integrite === null || $integrite['tete_lisible'] === true;

            return [
                'prete' => $moteurOk && $tablesOk && $integriteOk,
                'moteur' => $moteur,
                'tables' => $tablesOk,
                'integrite' => $integrite,
                'motif' => match (true) {
                    ! $moteurOk => 'PostgreSQL obligatoire en production',
                    ! $tablesOk => 'migration absente ou incomplète',
                    ! $integriteOk => 'chaîne d’audit invalide',
                    default => null,
                },
            ];
        } catch (\Throwable $e) {
            return [
                'prete' => false,
                'moteur' => null,
                'tables' => false,
                'motif' => 'connexion indisponible ('.$e::class.')',
            ];
        }
    }

    /**
     * Laravel expose les valeurs de .env dans $_ENV et $_SERVER. getenv()
     * seul ferait déclarer les connexions absentes et réactiverait SQLite.
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
            'POLICY_REGISTRY_URL' => 'database.connections.gamad_policies.url',
            'CONTRACT_REGISTRY_URL' => 'database.connections.gamad_contracts.url',
            default => null,
        };
        $valeurLaravel = $configuration === null ? null : config($configuration);

        foreach ([getenv($nom), $valeurLaravel, $_ENV[$nom] ?? null, $_SERVER[$nom] ?? null] as $valeur) {
            if (is_string($valeur)) {
                return $valeur;
            }
        }

        return null;
    }

    private function configurationPasskeysFermee(): bool
    {
        $url = rtrim((string) config('app.url'), '/');
        $hote = parse_url($url, PHP_URL_HOST);
        $rpId = (string) config('passkeys.relying_party.id');
        $origines = config('passkeys.allowed_origins', []);
        if (! is_string($hote)
            || $hote === ''
            || $rpId === ''
            || ! hash_equals($hote, $rpId)
            || ! is_array($origines)
            || ! in_array($url, $origines, true)) {
            return false;
        }

        foreach ($origines as $origine) {
            if (! is_string($origine)
                || parse_url($origine, PHP_URL_SCHEME) !== 'https'
                || parse_url($origine, PHP_URL_HOST) === null) {
                return false;
            }
        }

        return true;
    }
}
