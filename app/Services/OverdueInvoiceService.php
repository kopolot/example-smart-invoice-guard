<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Notifications\InvoiceOverdueReminder;
use Carbon\CarbonInterface;

class OverdueInvoiceService
{
    public function markOverdue(?CarbonInterface $asOf = null): int
    {
        $asOf ??= now();
        $marked = 0;

        Invoice::query()
            ->overdueCandidates($asOf)
            ->orderBy('id')
            ->chunkById(100, function ($invoices) use (&$marked): void {
                foreach ($invoices as $invoice) {
                    $invoice->update([
                        'status' => InvoiceStatus::OVERDUE,
                    ]);

                    $marked++;
                }
            });

        return $marked;
    }

    public function sendReminders(?CarbonInterface $asOf = null): int
    {
        $asOf ??= now();
        $sent = 0;

        Invoice::query()
            ->with('user')
            ->where('status', InvoiceStatus::OVERDUE)
            ->whereNull('overdue_reminded_at')
            ->orderBy('id')
            ->chunkById(100, function ($invoices) use (&$sent, $asOf): void {
                foreach ($invoices as $invoice) {
                    if ($invoice->user === null) {
                        continue;
                    }

                    $invoice->user->notify(new InvoiceOverdueReminder($invoice));

                    $invoice->forceFill([
                        'overdue_reminded_at' => $asOf,
                    ])->save();

                    $sent++;
                }
            });

        return $sent;
    }
}
