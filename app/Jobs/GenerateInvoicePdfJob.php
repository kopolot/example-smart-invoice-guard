<?php

namespace App\Jobs;

use App\Events\InvoicePdfGenerated;
use App\Models\Invoice;
use App\Services\PdfMaker;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class GenerateInvoicePdfJob implements ShouldQueue
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
    public function __construct(private Invoice $invoice)
    {
        $this->onConnection('rabbitmq-invoices');
        $this->onQueue('pdf');
    }

    /**
     * Execute the job.
     */
    public function handle(PdfMaker $pdfMaker): void
    {
        // not perfect but it works for now
        // TODO: private storage and PdfFileController to handle the pdf file
        $path = 'invoices/'.$this->invoice->user_id.'/'.$this->invoice->id.'_'.date('Y-m-d_H-i-s').'.pdf';
        try {
            throw_unless(
                $pdfMaker->make($this->invoice, $path),
                \Exception::class,
                'Failed to make pdf file'
            );
            throw_unless(
                $this->invoice->update([
                    'pdf_path' => $path,
                ]),
                \Exception::class,
                'Failed to update invoice'
            );
            InvoicePdfGenerated::dispatch($this->invoice);
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($path);

            throw $e;
        }
    }
}
