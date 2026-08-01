<?php

namespace App\Observers;

use App\Models\Invoice;
use App\Services\DashboardStatsService;
use App\Services\InvoicePulseService;
use App\Services\InvoiceSearchService;

class InvoiceObserver
{
    public function __construct(
        private DashboardStatsService $dashboardStatsService,
        private InvoicePulseService $invoicePulseService,
        private InvoiceSearchService $invoiceSearchService,
    ) {}

    /**
     * Handle the Invoice "created" event.
     */
    public function created(Invoice $invoice): void
    {
        $invoice->statusHistories()->create([
            'status' => $invoice->status,
        ]);

        $this->invalidateDashboard($invoice);
        $this->syncSearchIndex($invoice);
    }

    /**
     * Handle the Invoice "updated" event.
     */
    public function updated(Invoice $invoice): void
    {
        if ($invoice->wasChanged('status')) {
            $invoice->statusHistories()->create([
                'status' => $invoice->status,
            ]);
        }

        $this->invalidateDashboard($invoice);
        $this->syncSearchIndex($invoice);
    }

    /**
     * Handle the Invoice "deleted" event.
     */
    public function deleted(Invoice $invoice): void
    {
        $this->invalidateDashboard($invoice);
        $this->invoicePulseService->forget($invoice);
        $this->forgetSearchIndex($invoice);
    }

    /**
     * Handle the Invoice "restored" event.
     */
    public function restored(Invoice $invoice): void
    {
        $this->invalidateDashboard($invoice);
        $this->syncSearchIndex($invoice);
    }

    /**
     * Handle the Invoice "force deleted" event.
     */
    public function forceDeleted(Invoice $invoice): void
    {
        $this->invalidateDashboard($invoice);
        $this->invoicePulseService->forget($invoice);
        $this->forgetSearchIndex($invoice);
    }

    private function invalidateDashboard(Invoice $invoice): void
    {
        $this->dashboardStatsService->forgetForUser((int) $invoice->user_id);
    }

    private function syncSearchIndex(Invoice $invoice): void
    {
        try {
            $this->invoiceSearchService->index($invoice);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    private function forgetSearchIndex(Invoice $invoice): void
    {
        try {
            $this->invoiceSearchService->forget($invoice);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
