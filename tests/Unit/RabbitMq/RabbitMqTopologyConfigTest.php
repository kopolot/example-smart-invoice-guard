<?php

namespace Tests\Unit\RabbitMq;

use App\RabbitMq\RabbitMqTopology;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RabbitMqTopologyConfigTest extends TestCase
{
    #[Test]
    public function topology_config_splits_invoices_and_email_domains(): void
    {
        $topology = new RabbitMqTopology;
        $invoices = $topology->domain('invoices');
        $email = $topology->domain('email');

        $this->assertSame('rabbitmq-invoices', $invoices['connection']);
        $this->assertSame('invoices.jobs', $invoices['jobs_exchange']);
        $this->assertSame('direct', $invoices['jobs_exchange_type']);
        $this->assertSame('invoices.events', $invoices['events_exchange']);
        $this->assertSame('topic', $invoices['events_exchange_type']);
        $this->assertSame('invoices.dlx', $invoices['dlx']);
        $this->assertContains('pdf', $invoices['queues']);

        $this->assertSame('rabbitmq-email', $email['connection']);
        $this->assertSame('email.jobs', $email['jobs_exchange']);
        $this->assertSame('email.dlx', $email['dlx']);
        $this->assertContains('email', $email['queues']);
        $this->assertArrayNotHasKey('events_exchange', $email);

        $this->assertSame('pdf.failed', $topology->failedQueueName('pdf'));
        $this->assertSame('email.failed', $topology->failedRoutingKey('email'));
    }
}
