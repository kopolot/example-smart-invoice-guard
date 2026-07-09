<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Notifications\InvoiceOverdueReminder;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class OverdueInvoiceService
{
    public function markOverdue(?CarbonInterface $asOf = null): int
    {
        $asOf ??= now();
        $marked = 0;

        Invoice::query()
            ->overdueCandidates($asOf)
            ->orderBy('id')
            ->chunkById(500, function ($invoices) use (&$marked, $asOf): void {
                $invoiceIds = $invoices->pluck('id')->all();

                if ($invoiceIds === []) {
                    return;
                }

                DB::transaction(function () use ($invoiceIds, $asOf, &$marked): void {
                    $updated = Invoice::query()
                        ->whereIn('id', $invoiceIds)
                        ->update([
                            'status' => InvoiceStatus::OVERDUE,
                            'updated_at' => $asOf,
                        ]);

                    if ($updated === 0) {
                        return;
                    }

                    DB::table('invoice_status_histories')->insert(
                        collect($invoiceIds)
                            ->map(fn (int $invoiceId): array => [
                                'invoice_id' => $invoiceId,
                                'status' => InvoiceStatus::OVERDUE->value,
                                'created_at' => $asOf,
                                'updated_at' => $asOf,
                            ])
                            ->all(),
                    );

                    $marked += $updated;
                });
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
