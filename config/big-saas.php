<?php

/**
 * Big SaaS — routing tenantów i mapowanie plan → infrastruktura.
 *
 * W produkcji tenant_id / plan pochodzi z control plane (tenant_registry DB).
 * Lokalnie można nadpisać przez BIG_SAAS_TENANT_ID / BIG_SAAS_TIER w .env.
 */
return [

    'enabled' => env('BIG_SAAS_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Control plane (rejestr tenantów)
    |--------------------------------------------------------------------------
    */
    'control_plane' => [
        'connection' => env('BIG_SAAS_CONTROL_DB_CONNECTION', 'control'),
        'database' => env('BIG_SAAS_CONTROL_DB_DATABASE', 'tenant_registry'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Plany i domyślne klastry danych
    |--------------------------------------------------------------------------
    |
    | normal   → shared PXC, DB per tenant (sig_shared_tenant_{id})
    | premium  → jeden z klastrów premium [1..3], shard po tenant_id
    | enterprise → dedykowany klaster sig-enterprise-{N}
    */
    'tiers' => [
        'normal' => [
            'data_connection' => env('BIG_SAAS_SHARED_DB_CONNECTION', 'shared'),
            'php_upstream' => 'shared',
            'database_prefix' => env('BIG_SAAS_SHARED_DB_PREFIX', 'sig_shared_tenant_'),
            'limits' => [
                'max_invoices' => 500,
                'api_rpm' => 60,
            ],
        ],
        'premium' => [
            'shard_count' => (int) env('BIG_SAAS_PREMIUM_CLUSTER_COUNT', 3),
            'data_connection_prefix' => 'premium_',
            'php_upstream_prefix' => 'premium-',
            'database_prefix' => env('BIG_SAAS_PREMIUM_DB_PREFIX', 'sig_premium_tenant_'),
            'limits' => [
                'max_invoices' => 50_000,
                'api_rpm' => 300,
            ],
        ],
        'enterprise' => [
            'data_connection_prefix' => 'enterprise_',
            'php_upstream_prefix' => 'enterprise-',
            'database_prefix' => env('BIG_SAAS_ENTERPRISE_DB_PREFIX', 'sig_enterprise_'),
            'limits' => [
                'max_invoices' => null,
                'api_rpm' => 1000,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rozwiązywanie klastra po tenant_id (deterministyczny shard)
    |--------------------------------------------------------------------------
    */
    'shard' => [
        'premium' => static function (int $tenantId, int $clusterCount): int {
            return (($tenantId - 1) % $clusterCount) + 1;
        },
    ],

    /*
    |--------------------------------------------------------------------------
    | Dev override — symulacja tenant context bez control plane
    |--------------------------------------------------------------------------
    */
    'dev' => [
        'tenant_id' => env('BIG_SAAS_TENANT_ID'),
        'tier' => env('BIG_SAAS_TIER', 'normal'),
        'premium_cluster' => env('BIG_SAAS_PREMIUM_CLUSTER'),
        'enterprise_cluster' => env('BIG_SAAS_ENTERPRISE_CLUSTER'),
    ],

];
