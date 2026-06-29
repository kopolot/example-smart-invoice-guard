<?php

namespace App\Listeners;

use App\Enums\InvoiceStatus;
use App\Events\InvoicePaid;
use Illuminate\Support\Facades\DB;
use App\Models\Invoice;

class UpdateInvoiceStatus
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(InvoicePaid $event): void
    {
        DB::transaction(function () use ($event) {
            $invoice = Invoice::lockForUpdate()->findOrFail($event->getInvoice()->id);

            if ($invoice->status->value === InvoiceStatus::PAID->value) {
                return;
            }

            $invoice->update([
                'status' => InvoiceStatus::PAID,
            ]);
        });
    }
}
