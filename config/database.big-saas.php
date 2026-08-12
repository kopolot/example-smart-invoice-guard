<?php

/**
 * Big SaaS — dodatkowe połączenia DB (control / shared / premium / enterprise).
 * Dołączane do config/database.php gdy BIG_SAAS_ENABLED=true.
 *
 * @return array<string, array<string, mixed>>
 */
return array_filter([

    'control' => env('BIG_SAAS_ENABLED') ? [
        'driver' => 'mysql',
        'host' => env('BIG_SAAS_CONTROL_DB_HOST', 'control-db'),
        'port' => env('BIG_SAAS_CONTROL_DB_PORT', '3306'),
        'database' => env('BIG_SAAS_CONTROL_DB_DATABASE', 'tenant_registry'),
        'username' => env('BIG_SAAS_CONTROL_DB_USERNAME', 'laravel'),
        'password' => env('BIG_SAAS_CONTROL_DB_PASSWORD', 'laravel'),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'prefix_indexes' => true,
        'strict' => true,
        'engine' => null,
    ] : null,

    'shared' => env('BIG_SAAS_ENABLED') ? [
        'driver' => 'mysql',
        'host' => env('BIG_SAAS_SHARED_DB_HOST', 'shared-db'),
        'port' => env('BIG_SAAS_SHARED_DB_PORT', '3306'),
        'database' => env('BIG_SAAS_SHARED_DB_DATABASE', 'laravel'),
        'username' => env('BIG_SAAS_SHARED_DB_USERNAME', 'laravel'),
        'password' => env('BIG_SAAS_SHARED_DB_PASSWORD', 'laravel'),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'prefix_indexes' => true,
        'strict' => true,
        'engine' => null,
    ] : null,

    'premium_1' => env('BIG_SAAS_ENABLED') ? [
        'driver' => 'mysql',
        'host' => env('BIG_SAAS_PREMIUM_1_DB_HOST', 'premium-db-1'),
        'port' => env('BIG_SAAS_PREMIUM_1_DB_PORT', '3306'),
        'database' => env('BIG_SAAS_PREMIUM_1_DB_DATABASE', 'laravel'),
        'username' => env('BIG_SAAS_PREMIUM_1_DB_USERNAME', 'laravel'),
        'password' => env('BIG_SAAS_PREMIUM_1_DB_PASSWORD', 'laravel'),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'prefix_indexes' => true,
        'strict' => true,
        'engine' => null,
    ] : null,

    'premium_2' => env('BIG_SAAS_ENABLED') ? [
        'driver' => 'mysql',
        'host' => env('BIG_SAAS_PREMIUM_2_DB_HOST', 'premium-db-2'),
        'port' => env('BIG_SAAS_PREMIUM_2_DB_PORT', '3306'),
        'database' => env('BIG_SAAS_PREMIUM_2_DB_DATABASE', 'laravel'),
        'username' => env('BIG_SAAS_PREMIUM_2_DB_USERNAME', 'laravel'),
        'password' => env('BIG_SAAS_PREMIUM_2_DB_PASSWORD', 'laravel'),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'prefix_indexes' => true,
        'strict' => true,
        'engine' => null,
    ] : null,

    'premium_3' => env('BIG_SAAS_ENABLED') ? [
        'driver' => 'mysql',
        'host' => env('BIG_SAAS_PREMIUM_3_DB_HOST', 'premium-db-3'),
        'port' => env('BIG_SAAS_PREMIUM_3_DB_PORT', '3306'),
        'database' => env('BIG_SAAS_PREMIUM_3_DB_DATABASE', 'laravel'),
        'username' => env('BIG_SAAS_PREMIUM_3_DB_USERNAME', 'laravel'),
        'password' => env('BIG_SAAS_PREMIUM_3_DB_PASSWORD', 'laravel'),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'prefix_indexes' => true,
        'strict' => true,
        'engine' => null,
    ] : null,

    'enterprise_1' => env('BIG_SAAS_ENABLED') ? [
        'driver' => 'mysql',
        'host' => env('BIG_SAAS_ENTERPRISE_1_DB_HOST', 'enterprise-db-1'),
        'port' => env('BIG_SAAS_ENTERPRISE_1_DB_PORT', '3306'),
        'database' => env('BIG_SAAS_ENTERPRISE_1_DB_DATABASE', 'laravel'),
        'username' => env('BIG_SAAS_ENTERPRISE_1_DB_USERNAME', 'laravel'),
        'password' => env('BIG_SAAS_ENTERPRISE_1_DB_PASSWORD', 'laravel'),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'prefix_indexes' => true,
        'strict' => true,
        'engine' => null,
    ] : null,

]);
