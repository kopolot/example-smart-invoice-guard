<?php

namespace App\Listeners;

use App\Enums\InvoiceStatus;
use App\Events\InvoicePaid;

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
        $event->getInvoice()->update([
            'status' => InvoiceStatus::PAID,
        ]);
    }
}
