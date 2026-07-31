<?php

namespace App\Observers;

use App\Models\Invoice;
use App\Services\DashboardStatsService;
use App\Services\InvoicePulseService;

class InvoiceObserver
{
    public function __construct(
        private DashboardStatsService $dashboardStatsService,
        private InvoicePulseService $invoicePulseService,
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
    }

    /**
     * Handle the Invoice "deleted" event.
     */
    public function deleted(Invoice $invoice): void
    {
        $this->invalidateDashboard($invoice);
        $this->invoicePulseService->forget($invoice);
    }

    /**
     * Handle the Invoice "restored" event.
     */
    public function restored(Invoice $invoice): void
    {
        $this->invalidateDashboard($invoice);
    }

    /**
     * Handle the Invoice "force deleted" event.
     */
    public function forceDeleted(Invoice $invoice): void
    {
        $this->invalidateDashboard($invoice);
        $this->invoicePulseService->forget($invoice);
    }

    private function invalidateDashboard(Invoice $invoice): void
    {
        $this->dashboardStatsService->forgetForUser((int) $invoice->user_id);
    }
}
