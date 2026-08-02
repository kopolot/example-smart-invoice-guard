<?php

namespace Tests\Feature\Invoice;

use App\Enums\InvoiceDomainEvent;
use App\Events\InvoicePaid;
use App\Events\InvoicePdfGenerated;
use App\Events\InvoiceSent;
use App\Jobs\ProcessInvoiceDomainEventJob;
use App\Models\Invoice;
use App\Models\User;
use App\Services\InvoiceAuditLogger;
use App\Services\InvoiceMetricsRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InvoiceDomainEventPublishingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function invoice_lifecycle_events_publish_to_topic_routing_keys(): void
    {
        Queue::fake();
        Mail::fake();

        $invoice = Invoice::factory()->for(User::factory())->create([
            'pdf_path' => 'invoices/1/demo.pdf',
            'sent_at' => now(),
        ]);

        InvoicePdfGenerated::dispatch($invoice);
        InvoiceSent::dispatch($invoice);
        InvoicePaid::dispatch($invoice);

        Queue::assertPushedOn(
            InvoiceDomainEvent::PdfGenerated->routingKey(),
            ProcessInvoiceDomainEventJob::class,
        );
        Queue::assertPushedOn(
            InvoiceDomainEvent::Sent->routingKey(),
            ProcessInvoiceDomainEventJob::class,
        );
        Queue::assertPushedOn(
            InvoiceDomainEvent::Paid->routingKey(),
            ProcessInvoiceDomainEventJob::class,
        );

        Queue::assertPushed(
            ProcessInvoiceDomainEventJob::class,
            fn (ProcessInvoiceDomainEventJob $job): bool => $job->event === InvoiceDomainEvent::PdfGenerated
                && $job->invoiceId === $invoice->id,
        );

        Queue::assertPushed(
            ProcessInvoiceDomainEventJob::class,
            fn (ProcessInvoiceDomainEventJob $job): bool => $job->event === InvoiceDomainEvent::Paid
                && $job->invoiceId === $invoice->id,
        );
    }

    #[Test]
    public function domain_event_consumers_record_side_effects_per_queue(): void
    {
        $invoice = Invoice::factory()->for(User::factory())->create();
        $event = InvoiceDomainEvent::PdfGenerated;

        $metricsJob = new ProcessInvoiceDomainEventJob($invoice->id, $event);
        $metricsJob->queue = 'invoice.metrics';
        $metricsJob->handle(app(InvoiceMetricsRecorder::class), app(InvoiceAuditLogger::class));

        $auditJob = new ProcessInvoiceDomainEventJob($invoice->id, $event);
        $auditJob->queue = 'invoice.audit';
        $auditJob->handle(app(InvoiceMetricsRecorder::class), app(InvoiceAuditLogger::class));

        $webhookJob = new ProcessInvoiceDomainEventJob($invoice->id, InvoiceDomainEvent::Paid);
        $webhookJob->queue = 'invoice.webhooks';
        $webhookJob->handle(app(InvoiceMetricsRecorder::class), app(InvoiceAuditLogger::class));

        $this->assertSame(1, Cache::get(app(InvoiceMetricsRecorder::class)->cacheKey($event)));
        $this->assertNotNull(Cache::get(app(InvoiceAuditLogger::class)->cacheKey($invoice->id, $event)));
        $this->assertNotNull(Cache::get($webhookJob->webhookCacheKey()));
    }
}
