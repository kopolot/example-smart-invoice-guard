<?php

namespace App\Services\BigSaas;

/**
 * Rozwiązuje połączenie DB, pulę PHP-FPM i limity zasobów dla tenant context.
 */
class TenantContextResolver
{
    /**
     * @return array{
     *     tenant_id: int|null,
     *     tier: string,
     *     connection: string,
     *     database: string,
     *     php_upstream: string,
     *     limits: array<string, int|null>
     * }
     */
    public function resolve(?int $tenantId = null, ?string $tier = null): array
    {
        if (! config('big-saas.enabled')) {
            return $this->defaultContext();
        }

        $tenantId ??= $this->devTenantId();
        $tier ??= $this->devTier();

        return match ($tier) {
            'premium' => $this->premiumContext($tenantId),
            'enterprise' => $this->enterpriseContext($tenantId),
            default => $this->normalContext($tenantId),
        };
    }

    /**
     * @return array{
     *     tenant_id: int|null,
     *     tier: string,
     *     connection: string,
     *     database: string,
     *     php_upstream: string,
     *     limits: array<string, int|null>
     * }
     */
    public function defaultContext(): array
    {
        return [
            'tenant_id' => null,
            'tier' => 'normal',
            'connection' => (string) config('database.default'),
            'database' => (string) config('database.connections.'.config('database.default').'.database'),
            'php_upstream' => 'default',
            'limits' => config('big-saas.tiers.normal.limits'),
        ];
    }

    /**
     * @return array{
     *     tenant_id: int|null,
     *     tier: string,
     *     connection: string,
     *     database: string,
     *     php_upstream: string,
     *     limits: array<string, int|null>
     * }
     */
    private function normalContext(int $tenantId): array
    {
        $tierConfig = config('big-saas.tiers.normal');

        return [
            'tenant_id' => $tenantId,
            'tier' => 'normal',
            'connection' => (string) $tierConfig['data_connection'],
            'database' => $tierConfig['database_prefix'].$tenantId,
            'php_upstream' => (string) $tierConfig['php_upstream'],
            'limits' => $tierConfig['limits'],
        ];
    }

    /**
     * @return array{
     *     tenant_id: int|null,
     *     tier: string,
     *     connection: string,
     *     database: string,
     *     php_upstream: string,
     *     limits: array<string, int|null>
     * }
     */
    private function premiumContext(int $tenantId): array
    {
        $tierConfig = config('big-saas.tiers.premium');
        $clusterCount = (int) $tierConfig['shard_count'];
        $shardFn = config('big-saas.shard.premium');
        $clusterId = is_callable($shardFn)
            ? (int) $shardFn($tenantId, $clusterCount)
            : 1;

        $override = config('big-saas.dev.premium_cluster');
        if ($override !== null) {
            $clusterId = (int) $override;
        }

        return [
            'tenant_id' => $tenantId,
            'tier' => 'premium',
            'connection' => $tierConfig['data_connection_prefix'].$clusterId,
            'database' => $tierConfig['database_prefix'].$tenantId,
            'php_upstream' => $tierConfig['php_upstream_prefix'].$clusterId,
            'limits' => $tierConfig['limits'],
        ];
    }

    /**
     * @return array{
     *     tenant_id: int|null,
     *     tier: string,
     *     connection: string,
     *     database: string,
     *     php_upstream: string,
     *     limits: array<string, int|null>
     * }
     */
    private function enterpriseContext(int $tenantId): array
    {
        $tierConfig = config('big-saas.tiers.enterprise');
        $clusterId = (int) (config('big-saas.dev.enterprise_cluster') ?? 1);

        return [
            'tenant_id' => $tenantId,
            'tier' => 'enterprise',
            'connection' => $tierConfig['data_connection_prefix'].$clusterId,
            'database' => $tierConfig['database_prefix'].$tenantId,
            'php_upstream' => $tierConfig['php_upstream_prefix'].$clusterId,
            'limits' => $tierConfig['limits'],
        ];
    }

    private function devTenantId(): int
    {
        $id = config('big-saas.dev.tenant_id');

        return $id !== null ? (int) $id : 1;
    }

    private function devTier(): string
    {
        return (string) config('big-saas.dev.tier', 'normal');
    }
}
