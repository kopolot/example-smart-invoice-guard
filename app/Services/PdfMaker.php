<?php

namespace App\Services;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class PdfMaker
{
    /**
     * Make a pdf file for the invoice
     *
     * @param Invoice $invoice
     * @return bool
     */
    public function make(Invoice $invoice, string $path): bool
    {
        $pdf = Pdf::loadView('pdf.invoice', ['invoice' => $invoice]);
        return Storage::disk('public')->put($path, $pdf->output());
    }
}
