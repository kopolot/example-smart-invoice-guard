<?php

namespace Tests\Feature\RabbitMq;

use App\RabbitMq\RabbitMqTopology;
use Illuminate\Support\Facades\Queue;
use PhpAmqpLib\Exception\AMQPIOException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;

class SetupRabbitMqTopologyTest extends TestCase
{
    #[Test]
    public function setup_topology_command_declares_domain_exchanges_and_queues(): void
    {
        if (! $this->rabbitMqIsReachable()) {
            $this->markTestSkipped('RabbitMQ is not reachable.');
        }

        $this->artisan('rabbitmq:setup-topology')
            ->assertSuccessful();

        $topology = app(RabbitMqTopology::class);

        /** @var RabbitMQQueue $queue */
        $queue = Queue::connection(config('rabbitmq.connection'));

        foreach ($topology->domains() as $domain) {
            $this->assertTrue($queue->isExchangeExists((string) $domain['jobs_exchange']));
            $this->assertTrue($queue->isExchangeExists((string) $domain['dlx']));

            if (! empty($domain['events_exchange'])) {
                $this->assertTrue($queue->isExchangeExists((string) $domain['events_exchange']));
            }

            foreach ($domain['queues'] as $name) {
                $this->assertTrue($queue->isQueueExists($name));
                $this->assertTrue($queue->isQueueExists($topology->failedQueueName($name)));
            }
        }
    }

    private function rabbitMqIsReachable(): bool
    {
        try {
            /** @var RabbitMQQueue $queue */
            $queue = Queue::connection('rabbitmq');
            $queue->size('default');

            return true;
        } catch (AMQPIOException|\Throwable) {
            return false;
        }
    }
}
