<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Events\InvoicePdfGenerated;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Services\PdfMaker;

class GenerateInvoicePdfJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(private Invoice $invoice)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(PdfMaker $pdfMaker): void
    {
        // not perfect but it works for now
        // TODO: private storage and PdfFileController to handle the pdf file
        $path = 'invoices/' . $this->invoice->user_id . '/' . $this->invoice->id . '_' . date('Y-m-d_H-i-s') . '.pdf';
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
