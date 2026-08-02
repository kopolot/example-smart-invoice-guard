<?php

namespace Tests\Unit\RabbitMq;

use App\RabbitMq\RabbitMqTopology;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RabbitMqTopologyConfigTest extends TestCase
{
    #[Test]
    public function topology_config_maps_failed_queues_from_work_queues(): void
    {
        $topology = new RabbitMqTopology;

        $this->assertSame('invoices.dlx', $topology->deadLetterExchange());
        $this->assertContains('pdf', $topology->queues());
        $this->assertContains('email', $topology->queues());
        $this->assertSame('pdf.failed', $topology->failedQueueName('pdf'));
        $this->assertSame('email.failed', $topology->failedRoutingKey('email'));
    }

    #[Test]
    public function rabbitmq_queue_driver_reroutes_failed_jobs_to_dlx(): void
    {
        $options = config('queue.connections.rabbitmq.options.queue');

        $this->assertTrue((bool) $options['reroute_failed']);
        $this->assertSame('invoices.dlx', $options['failed_exchange']);
        $this->assertSame('%s.failed', $options['failed_routing_key']);
    }
}
