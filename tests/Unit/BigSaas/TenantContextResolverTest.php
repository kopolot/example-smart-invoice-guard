<?php

namespace Tests\Unit\BigSaas;

use App\Services\BigSaas\TenantContextResolver;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TenantContextResolverTest extends TestCase
{
    private TenantContextResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new TenantContextResolver;
    }

    public function test_returns_default_context_when_big_saas_disabled(): void
    {
        Config::set('big-saas.enabled', false);
        Config::set('database.default', 'sqlite');

        $context = $this->resolver->resolve();

        $this->assertSame('normal', $context['tier']);
        $this->assertSame('sqlite', $context['connection']);
        $this->assertSame('default', $context['php_upstream']);
    }

    public function test_resolves_normal_tenant_database_per_tenant(): void
    {
        $this->enableBigSaas();

        $context = $this->resolver->resolve(42, 'normal');

        $this->assertSame(42, $context['tenant_id']);
        $this->assertSame('normal', $context['tier']);
        $this->assertSame('shared', $context['connection']);
        $this->assertSame('sig_shared_tenant_42', $context['database']);
        $this->assertSame('shared', $context['php_upstream']);
        $this->assertSame(500, $context['limits']['max_invoices']);
    }

    #[DataProvider('premiumShardProvider')]
    public function test_resolves_premium_shard_by_tenant_id(int $tenantId, int $expectedCluster): void
    {
        $this->enableBigSaas();

        $context = $this->resolver->resolve($tenantId, 'premium');

        $this->assertSame('premium', $context['tier']);
        $this->assertSame('premium_'.$expectedCluster, $context['connection']);
        $this->assertSame('premium-'.$expectedCluster, $context['php_upstream']);
        $this->assertSame('sig_premium_tenant_'.$tenantId, $context['database']);
    }

    /**
     * @return array<string, array{0: int, 1: int}>
     */
    public static function premiumShardProvider(): array
    {
        return [
            'tenant 1 → cluster 1' => [1, 1],
            'tenant 2 → cluster 2' => [2, 2],
            'tenant 3 → cluster 3' => [3, 3],
            'tenant 4 → cluster 1' => [4, 1],
        ];
    }

    public function test_resolves_enterprise_dedicated_cluster(): void
    {
        $this->enableBigSaas();
        Config::set('big-saas.dev.enterprise_cluster', 1);

        $context = $this->resolver->resolve(7, 'enterprise');

        $this->assertSame('enterprise', $context['tier']);
        $this->assertSame('enterprise_1', $context['connection']);
        $this->assertSame('enterprise-1', $context['php_upstream']);
        $this->assertSame('sig_enterprise_7', $context['database']);
        $this->assertNull($context['limits']['max_invoices']);
        $this->assertSame(1000, $context['limits']['api_rpm']);
    }

    private function enableBigSaas(): void
    {
        Config::set('big-saas.enabled', true);
        Config::set('big-saas.tiers.normal', [
            'data_connection' => 'shared',
            'php_upstream' => 'shared',
            'database_prefix' => 'sig_shared_tenant_',
            'limits' => ['max_invoices' => 500, 'api_rpm' => 60],
        ]);
        Config::set('big-saas.tiers.premium', [
            'shard_count' => 3,
            'data_connection_prefix' => 'premium_',
            'php_upstream_prefix' => 'premium-',
            'database_prefix' => 'sig_premium_tenant_',
            'limits' => ['max_invoices' => 50_000, 'api_rpm' => 300],
        ]);
        Config::set('big-saas.tiers.enterprise', [
            'data_connection_prefix' => 'enterprise_',
            'php_upstream_prefix' => 'enterprise-',
            'database_prefix' => 'sig_enterprise_',
            'limits' => ['max_invoices' => null, 'api_rpm' => 1000],
        ]);
        Config::set('big-saas.shard.premium', static fn (int $tenantId, int $clusterCount): int => (($tenantId - 1) % $clusterCount) + 1);
        Config::set('big-saas.dev.tenant_id', null);
        Config::set('big-saas.dev.tier', 'normal');
        Config::set('big-saas.dev.premium_cluster', null);
        Config::set('big-saas.dev.enterprise_cluster', null);
    }
}
