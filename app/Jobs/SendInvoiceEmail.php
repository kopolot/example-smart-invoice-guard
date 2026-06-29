<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\Invoice;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\InvoiceSentMail;
use App\Events\InvoiceSent;

class SendInvoiceEmail implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(private Invoice $invoice, private string $email)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $invoice = $this->invoice;
        if ($invoice->sent_at) {
            Log::info('Invoice already sent', ['invoice_id' => $invoice->id]);
            return;
        }
        try {
            DB::transaction(function () use ($invoice) {
                $invoice = Invoice::lockForUpdate()->find($this->invoice->id);

                if ($invoice->sent_at) {
                    return;
                }

                $invoice->update(['sent_at' => now()]);
                DB::afterCommit(function () use ($invoice) {
                    Mail::to($this->email)->send(new InvoiceSentMail($invoice));
                    InvoiceSent::dispatch($invoice);
                });
            });
        } catch (\Throwable $e) {
            Log::error('Failed to send invoice email', ['invoice_id' => $invoice->id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }



    public function uniqueId(): string
    {
        return $this->invoice->id;
    }
}
