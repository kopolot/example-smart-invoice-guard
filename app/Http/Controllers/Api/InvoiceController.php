<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Http\Requests\Api\GenerateInvoicePdf;
use App\Services\PdfMaker;
use Illuminate\Support\Facades\Storage;

class InvoiceController extends Controller
{
    public function generate(GenerateInvoicePdf $request, PdfMaker $pdfMaker)
    {
        $invoice = Invoice::make($request->validated());
        $path = 'invoices/' .
            uniqid() . '_' . $invoice->id . '_' . date('Y-m-d_H-i-s') . '.pdf';
        $pdfMaker->make($invoice, $path);

        $pdfUrl = Storage::disk('public')->url($path);

        return response()->json(['pdf_url' => $pdfUrl]);
    }
}
