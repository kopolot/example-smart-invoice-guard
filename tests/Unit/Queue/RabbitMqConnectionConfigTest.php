<?php

namespace Tests\Unit\Queue;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RabbitMqConnectionConfigTest extends TestCase
{
    #[Test]
    public function rabbitmq_queue_connection_is_configured(): void
    {
        $connection = config('queue.connections.rabbitmq');

        $this->assertIsArray($connection);
        $this->assertSame('rabbitmq', $connection['driver']);
        $this->assertSame('default', $connection['connection']);
        $this->assertArrayHasKey('hosts', $connection);
        $this->assertNotEmpty($connection['hosts']);
        $this->assertArrayHasKey('host', $connection['hosts'][0]);
        $this->assertArrayHasKey('port', $connection['hosts'][0]);
        $this->assertArrayHasKey('user', $connection['hosts'][0]);
        $this->assertArrayHasKey('password', $connection['hosts'][0]);
        $this->assertArrayHasKey('vhost', $connection['hosts'][0]);
    }

    #[Test]
    public function domain_rabbitmq_connections_use_separate_jobs_and_dlx_exchanges(): void
    {
        $invoices = config('queue.connections.rabbitmq-invoices');
        $email = config('queue.connections.rabbitmq-email');

        $this->assertSame('rabbitmq', $invoices['driver']);
        $this->assertSame('pdf', $invoices['queue']);
        $this->assertTrue((bool) $invoices['options']['queue']['reroute_failed']);
        $this->assertSame('invoices.jobs', $invoices['options']['queue']['exchange']);
        $this->assertSame('direct', $invoices['options']['queue']['exchange_type']);
        $this->assertSame('invoices.dlx', $invoices['options']['queue']['failed_exchange']);
        $this->assertSame('%s.failed', $invoices['options']['queue']['failed_routing_key']);

        $this->assertSame('rabbitmq', $email['driver']);
        $this->assertSame('email', $email['queue']);
        $this->assertTrue((bool) $email['options']['queue']['reroute_failed']);
        $this->assertSame('email.jobs', $email['options']['queue']['exchange']);
        $this->assertSame('direct', $email['options']['queue']['exchange_type']);
        $this->assertSame('email.dlx', $email['options']['queue']['failed_exchange']);
        $this->assertSame('%s.failed', $email['options']['queue']['failed_routing_key']);
    }
}
