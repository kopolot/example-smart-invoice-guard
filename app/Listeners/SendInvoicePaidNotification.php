<?php

namespace App\Listeners;

use App\Events\InvoicePaid;
use App\Notifications\InvoicePaid as InvoicePaidNotification;

class SendInvoicePaidNotification
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
        $event->getInvoice()->user->notify(new InvoicePaidNotification($event->getInvoice()));
    }
}
