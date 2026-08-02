<?php

namespace Tests\Unit\Jobs;

use App\Jobs\GenerateInvoicePdfJob;
use App\Jobs\SendInvoiceEmail;
use App\Models\Invoice;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InvoiceQueueRetryConfigTest extends TestCase
{
    #[Test]
    public function invoice_jobs_use_domain_connections_and_exponential_backoff(): void
    {
        $invoice = new Invoice;
        $invoice->id = 1;

        $pdfJob = new GenerateInvoicePdfJob($invoice);
        $emailJob = new SendInvoiceEmail($invoice, 'test@example.com');

        $this->assertSame(3, $pdfJob->tries);
        $this->assertSame([1, 5, 10], $pdfJob->backoff);
        $this->assertSame('pdf', $pdfJob->queue);
        $this->assertSame('rabbitmq-invoices', $pdfJob->connection);

        $this->assertSame(3, $emailJob->tries);
        $this->assertSame([1, 5, 10], $emailJob->backoff);
        $this->assertSame('email', $emailJob->queue);
        $this->assertSame('rabbitmq-email', $emailJob->connection);
    }
}
