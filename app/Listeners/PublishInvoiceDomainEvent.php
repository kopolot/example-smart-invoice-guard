<?php

namespace App\Listeners;

use App\Enums\InvoiceDomainEvent;
use App\Events\InvoicePaid;
use App\Events\InvoicePdfGenerated;
use App\Events\InvoiceSent;
use App\Services\InvoiceDomainEventPublisher;

class PublishInvoiceDomainEvent
{
    public function __construct(private InvoiceDomainEventPublisher $publisher) {}

    public function handle(InvoicePdfGenerated|InvoiceSent|InvoicePaid $event): void
    {
        $domainEvent = match (true) {
            $event instanceof InvoicePdfGenerated => InvoiceDomainEvent::PdfGenerated,
            $event instanceof InvoiceSent => InvoiceDomainEvent::Sent,
            $event instanceof InvoicePaid => InvoiceDomainEvent::Paid,
        };

        $this->publisher->publish($event->getInvoice()->id, $domainEvent);
    }
}
