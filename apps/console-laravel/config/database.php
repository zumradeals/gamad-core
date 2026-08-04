<?php

use Illuminate\Support\Str;
use Pdo\Mysql;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for database operations. This is
    | the connection which will be utilized unless another connection
    | is explicitly specified when you execute a query / statement.
    |
    */

    'default' => env('DB_CONNECTION', 'sqlite'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Below are all of the database connections defined for your application.
    | An example configuration is provided for each database system which
    | is supported by Laravel. You're free to add / remove connections.
    |
    */

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
            'transaction_mode' => 'DEFERRED',
        ],

        /*
         * Connexions physiques de « Core Operational Foundation v1 ».
         *
         * Les quatre noms restent stables dans le code tandis que leurs URL
         * sont indépendantes. En production, les variables *_DRIVER valent
         * toutes `pgsql`; SQLite reste autorisé uniquement en local et en CI.
         */
        'gamad_index' => [
            'driver' => env('GAMAD_INDEX_DRIVER', 'sqlite'),
            'url' => env('DATABASE_URL'),
            'host' => env('GAMAD_INDEX_HOST', '127.0.0.1'),
            'port' => env('GAMAD_INDEX_PORT', '5432'),
            'database' => env('SQLITE_PATH', database_path('gamad-index.sqlite')),
            'username' => env('GAMAD_INDEX_USERNAME'),
            'password' => env('GAMAD_INDEX_PASSWORD'),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => env('GAMAD_INDEX_SSLMODE', 'prefer'),
            'foreign_key_constraints' => true,
        ],

        'gamad_access' => [
            'driver' => env('GAMAD_ACCESS_DRIVER', 'sqlite'),
            'url' => env('MAGASIN_URL'),
            'host' => env('GAMAD_ACCESS_HOST', '127.0.0.1'),
            'port' => env('GAMAD_ACCESS_PORT', '5432'),
            'database' => env('MAGASIN_PATH', database_path('gamad-access.sqlite')),
            'username' => env('GAMAD_ACCESS_USERNAME'),
            'password' => env('GAMAD_ACCESS_PASSWORD'),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => env('GAMAD_ACCESS_SSLMODE', 'prefer'),
            'foreign_key_constraints' => true,
        ],

        'gamad_identity' => [
            'driver' => env('GAMAD_IDENTITY_DRIVER', 'sqlite'),
            'url' => env('IDENTITY_REGISTRY_URL'),
            'host' => env('GAMAD_IDENTITY_HOST', '127.0.0.1'),
            'port' => env('GAMAD_IDENTITY_PORT', '5432'),
            'database' => env('IDENTITY_REGISTRY_PATH', database_path('gamad-identity.sqlite')),
            'username' => env('GAMAD_IDENTITY_USERNAME'),
            'password' => env('GAMAD_IDENTITY_PASSWORD'),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => env('GAMAD_IDENTITY_SSLMODE', 'prefer'),
            'foreign_key_constraints' => true,
        ],

        'gamad_journal' => [
            'driver' => env('GAMAD_JOURNAL_DRIVER', 'sqlite'),
            'url' => env('JOURNAL_OPERATIONNEL_URL'),
            'host' => env('GAMAD_JOURNAL_HOST', '127.0.0.1'),
            'port' => env('GAMAD_JOURNAL_PORT', '5432'),
            'database' => env('JOURNAL_OPERATIONNEL_PATH', database_path('gamad-journal.sqlite')),
            'username' => env('GAMAD_JOURNAL_USERNAME'),
            'password' => env('GAMAD_JOURNAL_PASSWORD'),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => env('GAMAD_JOURNAL_SSLMODE', 'prefer'),
            'foreign_key_constraints' => true,
        ],

        'gamad_products' => [
            'driver' => env('GAMAD_PRODUCTS_DRIVER', 'sqlite'),
            'url' => env('PRODUCT_REGISTRY_URL'),
            'host' => env('GAMAD_PRODUCTS_HOST', '127.0.0.1'),
            'port' => env('GAMAD_PRODUCTS_PORT', '5432'),
            'database' => env('PRODUCT_REGISTRY_PATH', database_path('gamad-products.sqlite')),
            'username' => env('GAMAD_PRODUCTS_USERNAME'),
            'password' => env('GAMAD_PRODUCTS_PASSWORD'),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => env('GAMAD_PRODUCTS_SSLMODE', 'prefer'),
            'foreign_key_constraints' => true,
        ],

        'gamad_sources' => [
            'driver' => env('GAMAD_SOURCES_DRIVER', 'sqlite'),
            'url' => env('SOURCE_REGISTRY_URL'),
            'host' => env('GAMAD_SOURCES_HOST', '127.0.0.1'),
            'port' => env('GAMAD_SOURCES_PORT', '5432'),
            'database' => env('SOURCE_REGISTRY_PATH', database_path('gamad-sources.sqlite')),
            'username' => env('GAMAD_SOURCES_USERNAME'),
            'password' => env('GAMAD_SOURCES_PASSWORD'),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => env('GAMAD_SOURCES_SSLMODE', 'prefer'),
            'foreign_key_constraints' => true,
        ],

        'gamad_policies' => [
            'driver' => env('GAMAD_POLICIES_DRIVER', 'sqlite'),
            'url' => env('POLICY_REGISTRY_URL'),
            'host' => env('GAMAD_POLICIES_HOST', '127.0.0.1'),
            'port' => env('GAMAD_POLICIES_PORT', '5432'),
            'database' => env('POLICY_REGISTRY_PATH', database_path('gamad-policies.sqlite')),
            'username' => env('GAMAD_POLICIES_USERNAME'),
            'password' => env('GAMAD_POLICIES_PASSWORD'),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => env('GAMAD_POLICIES_SSLMODE', 'prefer'),
            'foreign_key_constraints' => true,
        ],

        'gamad_contracts' => [
            'driver' => env('GAMAD_CONTRACTS_DRIVER', 'sqlite'),
            'url' => env('CONTRACT_REGISTRY_URL'),
            'host' => env('GAMAD_CONTRACTS_HOST', '127.0.0.1'),
            'port' => env('GAMAD_CONTRACTS_PORT', '5432'),
            'database' => env('CONTRACT_REGISTRY_PATH', database_path('gamad-contracts.sqlite')),
            'username' => env('GAMAD_CONTRACTS_USERNAME'),
            'password' => env('GAMAD_CONTRACTS_PASSWORD'),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => env('GAMAD_CONTRACTS_SSLMODE', 'prefer'),
            'foreign_key_constraints' => true,
        ],

        'gamad_vocabulary' => [
            'driver' => env('GAMAD_VOCABULARY_DRIVER', 'sqlite'),
            'url' => env('VOCABULARY_REGISTRY_URL'),
            'host' => env('GAMAD_VOCABULARY_HOST', '127.0.0.1'),
            'port' => env('GAMAD_VOCABULARY_PORT', '5432'),
            'database' => env('VOCABULARY_REGISTRY_PATH', database_path('gamad-vocabulary.sqlite')),
            'username' => env('GAMAD_VOCABULARY_USERNAME'),
            'password' => env('GAMAD_VOCABULARY_PASSWORD'),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => env('GAMAD_VOCABULARY_SSLMODE', 'prefer'),
            'foreign_key_constraints' => true,
        ],

        'gamad_organizations' => [
            'driver' => env('GAMAD_ORGANIZATIONS_DRIVER', 'sqlite'),
            'url' => env('ORGANIZATION_REGISTRY_URL'),
            'host' => env('GAMAD_ORGANIZATIONS_HOST', '127.0.0.1'),
            'port' => env('GAMAD_ORGANIZATIONS_PORT', '5432'),
            'database' => env('ORGANIZATION_REGISTRY_PATH', database_path('gamad-organizations.sqlite')),
            'username' => env('GAMAD_ORGANIZATIONS_USERNAME'),
            'password' => env('GAMAD_ORGANIZATIONS_PASSWORD'),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => env('GAMAD_ORGANIZATIONS_SSLMODE', 'prefer'),
            'foreign_key_constraints' => true,
        ],

        'gamad_realms' => [
            'driver' => env('GAMAD_REALMS_DRIVER', 'sqlite'),
            'url' => env('REALM_REGISTRY_URL'),
            'host' => env('GAMAD_REALMS_HOST', '127.0.0.1'),
            'port' => env('GAMAD_REALMS_PORT', '5432'),
            'database' => env('REALM_REGISTRY_PATH', database_path('gamad-realms.sqlite')),
            'username' => env('GAMAD_REALMS_USERNAME'),
            'password' => env('GAMAD_REALMS_PASSWORD'),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => env('GAMAD_REALMS_SSLMODE', 'prefer'),
            'foreign_key_constraints' => true,
        ],

        'gamad_evenements' => [
            'driver' => env('GAMAD_EVENT_DRIVER', 'sqlite'),
            'url' => env('EVENT_JOURNAL_URL'),
            'host' => env('GAMAD_EVENEMENTS_HOST', '127.0.0.1'),
            'port' => env('GAMAD_EVENEMENTS_PORT', '5432'),
            'database' => env('EVENT_JOURNAL_PATH', database_path('gamad-evenements.sqlite')),
            'username' => env('GAMAD_EVENEMENTS_USERNAME'),
            'password' => env('GAMAD_EVENEMENTS_PASSWORD'),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => env('GAMAD_EVENEMENTS_SSLMODE', 'prefer'),
            'foreign_key_constraints' => true,
        ],

        'gamad_secrets' => [
            'driver' => env('GAMAD_SECRETS_DRIVER', 'sqlite'),
            'url' => env('SECRET_REGISTRY_URL'),
            'host' => env('GAMAD_SECRETS_HOST', '127.0.0.1'),
            'port' => env('GAMAD_SECRETS_PORT', '5432'),
            'database' => env('SECRET_REGISTRY_PATH', database_path('gamad-secrets.sqlite')),
            'username' => env('GAMAD_SECRETS_USERNAME'),
            'password' => env('GAMAD_SECRETS_PASSWORD'),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => env('GAMAD_SECRETS_SSLMODE', 'prefer'),
            'foreign_key_constraints' => true,
        ],

        'gamad_preuves' => [
            'driver' => env('GAMAD_PROOFS_DRIVER', 'sqlite'),
            'url' => env('PROOF_REGISTRY_URL'),
            'host' => env('GAMAD_PREUVES_HOST', '127.0.0.1'),
            'port' => env('GAMAD_PREUVES_PORT', '5432'),
            'database' => env('PROOF_REGISTRY_PATH', database_path('gamad-preuves.sqlite')),
            'username' => env('GAMAD_PREUVES_USERNAME'),
            'password' => env('GAMAD_PREUVES_PASSWORD'),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => env('GAMAD_PREUVES_SSLMODE', 'prefer'),
            'foreign_key_constraints' => true,
        ],

        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                Mysql::ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'mariadb' => [
            'driver' => 'mariadb',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                Mysql::ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            // Même variable DATABASE_URL que Gamad\RegistreNormes\Db::connect() (CTR-04) — une
            // seule source de vérité pour la chaîne de connexion, jamais dupliquée.
            'url' => env('DATABASE_URL', env('DB_URL')),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => env('DB_SSLMODE', 'prefer'),
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', '1433'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            // 'encrypt' => env('DB_ENCRYPT', 'yes'),
            // 'trust_server_certificate' => env('DB_TRUST_SERVER_CERTIFICATE', 'false'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run on the database.
    |
    */

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer body of commands than a typical key-value system
    | such as Memcached. You may define your connection settings here.
    |
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')).'-database-'),
            'persistent' => env('REDIS_PERSISTENT', false),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

    ],

];
