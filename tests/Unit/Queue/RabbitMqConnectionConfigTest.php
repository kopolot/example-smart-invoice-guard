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
        $this->assertTrue((bool) $connection['options']['queue']['reroute_failed']);
        $this->assertSame('invoices.dlx', $connection['options']['queue']['failed_exchange']);
    }
}
