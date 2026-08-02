<?php

namespace App\Enums;

enum InvoiceDomainEvent: string
{
    case PdfGenerated = 'invoice.pdf.generated';
    case Sent = 'invoice.sent';
    case Paid = 'invoice.paid';

    public function routingKey(): string
    {
        return $this->value;
    }
}
