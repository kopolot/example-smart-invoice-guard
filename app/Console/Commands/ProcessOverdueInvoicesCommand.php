<?php

namespace App\Console\Commands;

use App\Services\OverdueInvoiceService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('invoices:process-overdue')]
#[Description('Mark overdue invoices and send reminder emails to invoice owners')]
class ProcessOverdueInvoicesCommand extends Command
{
    public function __construct(private OverdueInvoiceService $overdueInvoiceService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $marked = $this->overdueInvoiceService->markOverdue();
        $reminded = $this->overdueInvoiceService->sendReminders();

        $this->components->info("Marked {$marked} invoice(s) as overdue.");
        $this->components->info("Sent {$reminded} overdue reminder(s).");

        return self::SUCCESS;
    }
}
