<?php

namespace App\Jobs;

use App\Enums\InvoiceDomainEvent;
use App\Services\InvoiceAuditLogger;
use App\Services\InvoiceMetricsRecorder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Side-effects for invoice lifecycle events published to the invoices.events topic.
 *
 * Laravel Queue serializes one job class per publish; RabbitMQ topic fan-out copies
 * that same body into every bound queue (metrics/audit/webhooks). We therefore branch
 * on the consumed queue name instead of using separate job classes — a deliberate
 * trade-off between Laravel's work-queue model and AMQP-style consumers. See README.
 */
class ProcessInvoiceDomainEventJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [1, 5, 10];

    public function __construct(
        public int $invoiceId,
        public InvoiceDomainEvent $event,
    ) {}

    public function handle(
        InvoiceMetricsRecorder $metricsRecorder,
        InvoiceAuditLogger $auditLogger,
    ): void {
        // Same job body lands in each bound queue; queue name selects the side-effect.
        $consumedQueue = $this->job?->getQueue() ?? $this->queue;

        match ($consumedQueue) {
            'invoice.metrics' => $metricsRecorder->record($this->event),
            'invoice.audit' => $auditLogger->log($this->invoiceId, $this->event),
            'invoice.webhooks' => $this->dispatchWebhook(),
            default => Log::warning('Unhandled invoice domain event queue', [
                'queue' => $consumedQueue,
                'invoice_id' => $this->invoiceId,
                'event' => $this->event->value,
            ]),
        };
    }

    public function webhookCacheKey(): string
    {
        return 'invoice:webhook:'.$this->invoiceId.':'.$this->event->value;
    }

    private function dispatchWebhook(): void
    {
        // Simulated outbound webhook for portfolio/demo purposes.
        Cache::forever($this->webhookCacheKey(), [
            'invoice_id' => $this->invoiceId,
            'event' => $this->event->value,
            'dispatched_at' => now()->toIso8601String(),
        ]);

        Log::info('Invoice domain webhook dispatched', [
            'invoice_id' => $this->invoiceId,
            'event' => $this->event->value,
        ]);
    }
}
