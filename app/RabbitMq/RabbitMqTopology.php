<?php

namespace App\RabbitMq;

use Illuminate\Support\Facades\Queue as QueueFacade;
use PhpAmqpLib\Exchange\AMQPExchangeType;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\RabbitMQQueue;

class RabbitMqTopology
{
    /**
     * @return list<string>
     */
    public function queues(): array
    {
        /** @var list<string> $queues */
        $queues = config('rabbitmq.queues', []);

        return $queues;
    }

    public function deadLetterExchange(): string
    {
        return (string) config('rabbitmq.dlx.exchange');
    }

    public function deadLetterExchangeType(): string
    {
        $type = strtoupper((string) config('rabbitmq.dlx.type', AMQPExchangeType::DIRECT));
        $constant = AMQPExchangeType::class.'::'.$type;

        return defined($constant) ? constant($constant) : AMQPExchangeType::DIRECT;
    }

    public function failedRoutingKey(string $queue): string
    {
        return ltrim(sprintf((string) config('rabbitmq.failed_routing_key', '%s.failed'), $queue), '.');
    }

    public function failedQueueName(string $queue): string
    {
        return $this->failedRoutingKey($queue);
    }

    /**
     * Declare DLX, dead-letter queues, bindings, and work queues with DLX args.
     *
     * @return array{exchange: string, queues: list<string>, failed_queues: list<string>, refreshed: list<string>}
     */
    public function setup(bool $fresh = false, ?RabbitMQQueue $connection = null): array
    {
        $rabbitmq = $connection ?? $this->connection();
        $exchange = $this->deadLetterExchange();
        $declaredQueues = [];
        $declaredFailedQueues = [];
        $refreshed = [];

        if (! $rabbitmq->isExchangeExists($exchange)) {
            $rabbitmq->declareExchange(
                $exchange,
                $this->deadLetterExchangeType(),
                durable: true,
                autoDelete: false,
            );
        }

        foreach ($this->queues() as $queue) {
            $failedQueue = $this->failedQueueName($queue);
            $failedRoutingKey = $this->failedRoutingKey($queue);
            $workArguments = [
                'x-dead-letter-exchange' => $exchange,
                'x-dead-letter-routing-key' => $failedRoutingKey,
            ];

            if (! $rabbitmq->isQueueExists($failedQueue)) {
                $rabbitmq->declareQueue($failedQueue, durable: true, autoDelete: false);
            }

            $rabbitmq->bindQueue($failedQueue, $exchange, $failedRoutingKey);
            $declaredFailedQueues[] = $failedQueue;

            if ($fresh && $rabbitmq->isQueueExists($queue)) {
                $rabbitmq->deleteQueue($queue);
                $refreshed[] = $queue;
            }

            if (! $rabbitmq->isQueueExists($queue)) {
                $rabbitmq->declareQueue($queue, durable: true, autoDelete: false, arguments: $workArguments);
            }

            $declaredQueues[] = $queue;
        }

        return [
            'exchange' => $exchange,
            'queues' => $declaredQueues,
            'failed_queues' => $declaredFailedQueues,
            'refreshed' => $refreshed,
        ];
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
