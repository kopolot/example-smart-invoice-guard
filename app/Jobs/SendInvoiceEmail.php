<?php

namespace App\Jobs;

use App\Events\InvoiceSent;
use App\Mail\InvoiceSentMail;
use App\Models\Invoice;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendInvoiceEmail implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [1, 5, 10];

    /**
     * Create a new job instance.
     */
    public function __construct(private Invoice $invoice, private string $email)
    {
        $this->onConnection('rabbitmq-email');
        $this->onQueue('email');
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
