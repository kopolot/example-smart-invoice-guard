<?php

namespace App\Services;

use App\Enums\InvoiceDomainEvent;
use App\Jobs\ProcessInvoiceDomainEventJob;
use Illuminate\Support\Facades\Queue;

/**
 * Publishes one message to the invoices.events topic (routing key = domain event).
 * RabbitMQ fans it out to bound queues; Laravel Queue still carries a single job class.
 */
class InvoiceDomainEventPublisher
{
    public function publish(int $invoiceId, InvoiceDomainEvent $event): void
    {
        // Avoid requiring RabbitMQ during PHPUnit; topic fan-out is covered in dedicated tests.
        $connection = app()->runningUnitTests() ? 'sync' : 'rabbitmq-invoices-events';

        // pushOn(queue) is the AMQP routing key on the topic connection (not a work-queue name).
        Queue::connection($connection)->pushOn(
            $event->routingKey(),
            new ProcessInvoiceDomainEventJob($invoiceId, $event),
        );
    }
}
