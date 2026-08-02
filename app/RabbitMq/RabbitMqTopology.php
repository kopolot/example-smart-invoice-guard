<?php

namespace App\RabbitMq;

use Illuminate\Support\Facades\Queue as QueueFacade;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;

class RabbitMqTopology
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function domains(): array
    {
        /** @var array<string, array<string, mixed>> $domains */
        $domains = config('rabbitmq.domains', []);

        return $domains;
    }

    /**
     * @return array<string, mixed>
     */
    public function domain(string $name): array
    {
        $domains = $this->domains();

        if (! isset($domains[$name])) {
            throw new \InvalidArgumentException("Unknown RabbitMQ domain [{$name}].");
        }

        return $domains[$name];
    }

    public function failedRoutingKey(string $queue): string
    {
        return ltrim(sprintf((string) config('rabbitmq.failed_routing_key', '%s.failed'), $queue), '.');
    }

    public function failedQueueName(string $queue): string
    {
        return $this->failedRoutingKey($queue);
    }

    public function exchangeType(string $type): string
    {
        $constant = AMQPExchangeType::class.'::'.strtoupper($type);

        return defined($constant) ? constant($constant) : AMQPExchangeType::DIRECT;
    }

    /**
     * Declare per-domain jobs exchanges, DLX, failed queues, work queues, and reserved topic exchanges.
     *
     * @return array{
     *     jobs_exchanges: list<string>,
     *     events_exchanges: list<string>,
     *     dlx_exchanges: list<string>,
     *     queues: list<string>,
     *     failed_queues: list<string>,
     *     refreshed: list<string>
     * }
     */
    public function setup(bool $fresh = false, ?RabbitMQQueue $connection = null): array
    {
        $rabbitmq = $connection ?? $this->connection();
        $jobsExchanges = [];
        $eventsExchanges = [];
        $dlxExchanges = [];
        $declaredQueues = [];
        $declaredFailedQueues = [];
        $refreshed = [];

        foreach ($this->domains() as $domain) {
            $jobsExchange = (string) $domain['jobs_exchange'];
            $jobsExchangeType = $this->exchangeType((string) ($domain['jobs_exchange_type'] ?? 'direct'));
            $dlx = (string) $domain['dlx'];
            /** @var list<string> $queues */
            $queues = $domain['queues'];

            $this->declareExchangeIfMissing($rabbitmq, $jobsExchange, $jobsExchangeType);
            $jobsExchanges[] = $jobsExchange;

            $this->declareExchangeIfMissing($rabbitmq, $dlx, AMQPExchangeType::DIRECT);
            $dlxExchanges[] = $dlx;

            if (! empty($domain['events_exchange'])) {
                $eventsExchange = (string) $domain['events_exchange'];
                $eventsType = $this->exchangeType((string) ($domain['events_exchange_type'] ?? 'topic'));
                $this->declareExchangeIfMissing($rabbitmq, $eventsExchange, $eventsType);
                $eventsExchanges[] = $eventsExchange;
            }

            foreach ($queues as $queue) {
                $failedQueue = $this->failedQueueName($queue);
                $failedRoutingKey = $this->failedRoutingKey($queue);
                $workArguments = [
                    'x-dead-letter-exchange' => $dlx,
                    'x-dead-letter-routing-key' => $failedRoutingKey,
                ];

                if (! $rabbitmq->isQueueExists($failedQueue)) {
                    $rabbitmq->declareQueue($failedQueue, durable: true, autoDelete: false);
                }

                $rabbitmq->bindQueue($failedQueue, $dlx, $failedRoutingKey);
                $declaredFailedQueues[] = $failedQueue;

                if ($fresh && $rabbitmq->isQueueExists($queue)) {
                    $rabbitmq->deleteQueue($queue);
                    $refreshed[] = $queue;
                }

                if (! $rabbitmq->isQueueExists($queue)) {
                    $rabbitmq->declareQueue($queue, durable: true, autoDelete: false, arguments: $workArguments);
                }

                // Jobs publish to the domain jobs exchange with routing key = queue name.
                $rabbitmq->bindQueue($queue, $jobsExchange, $queue);
                $declaredQueues[] = $queue;
            }
        }

        return [
            'jobs_exchanges' => $jobsExchanges,
            'events_exchanges' => $eventsExchanges,
            'dlx_exchanges' => $dlxExchanges,
            'queues' => $declaredQueues,
            'failed_queues' => $declaredFailedQueues,
            'refreshed' => $refreshed,
        ];
    }

    protected function declareExchangeIfMissing(RabbitMQQueue $rabbitmq, string $name, string $type): void
    {
        if ($rabbitmq->isExchangeExists($name)) {
            return;
        }

        $rabbitmq->declareExchange($name, $type, durable: true, autoDelete: false);
    }

    protected function connection(): RabbitMQQueue
    {
        $connection = (string) config('rabbitmq.connection', 'rabbitmq');
        $queue = QueueFacade::connection($connection);

        if (! $queue instanceof RabbitMQQueue) {
            throw new \RuntimeException(
                "Queue connection [{$connection}] must use the rabbitmq driver to set up topology."
            );
        }

        return $queue;
    }
}
