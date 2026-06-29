<?php

namespace App\Http\Controllers;

use App\Events\InvoicePaid;
use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\UpdateInvoiceRequest;
use App\Jobs\GenerateInvoicePdfJob;
use App\Models\Invoice;
use App\Services\InvoicePriceCalculator;
use Illuminate\Support\Str;
use Inertia\Inertia;
use App\Jobs\SendInvoiceEmail;

class InvoiceController extends Controller
{
    public function __construct(private InvoicePriceCalculator $invoicePriceCalculator)
    {
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $perPage = 10;
        $page = request()->input('page', 1);
        // if is ajax request and no inertia request, return the invoices
        if (request()->ajax() && !request()->inertia()) {
            return response()->json([
                'invoicesPagination' => auth()->user()->invoices()->paginate($perPage, page: $page),
            ]);
        }
        $invoices = auth()->user()->invoices()->paginate($perPage, page: $page);
        return Inertia::render('invoices/Index', [
            'invoicesPagination' => $invoices,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('invoices/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreInvoiceRequest $request)
    {
        $total = $this->invoicePriceCalculator->calculateTotal($request->validated());
        $invoice = Invoice::create([
            'user_id' => $request->user()->id,
            'number' => $request->number,
            'amount' => $request->amount,
            'tax_rate' => $request->tax_rate,
            'tax_number' => $request->tax_number,
            'total_amount' => $total,
            'status' => $request->status,
            'date' => $request->date,
        ]);

        return redirect()->route('invoices.show', $invoice);
    }

    /**
     * Display the specified resource.
     */
    public function show(Invoice $invoice)
    {
        return Inertia::render('invoices/Show', [
            'invoice' => $invoice,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Invoice $invoice)
    {
        return Inertia::render('invoices/Edit', [
            'invoice' => $invoice,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateInvoiceRequest $request, Invoice $invoice)
    {
        $total = $this->invoicePriceCalculator->calculateTotal($request->validated());
        $invoice->update([
            'number' => $request->number,
            'amount' => $request->amount,
            'tax_rate' => $request->tax_rate,
            'tax_number' => $request->tax_number,
            'total_amount' => $total,
            'status' => $request->status,
            'date' => $request->date,
        ]);

        return redirect()->route('invoices.show', $invoice);
    }

    // /**
    //  * Remove the specified resource from storage.
    //  */
    // public function destroy(Invoice $invoice)
    // {
    //     $invoice->destroy();

    //     return redirect()->route('invoices.index');
    // }

    public function deleteMethod(Invoice $invoice)
    {
        $invoice->delete();

        return redirect()->route('invoices.index');
    }

    public function pdf(Invoice $invoice)
    {
        GenerateInvoicePdfJob::dispatch($invoice);

        return response()->json(['message' => __('Invoice will be generated in a few seconds.')]);
    }

    public function pay(Invoice $invoice)
    {
        if ($invoice->status?->value === 'paid') {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Invoice already paid.')]);

            return redirect(route('invoices.show', $invoice));
        }
        InvoicePaid::dispatch($invoice);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invoice paid successfully.')]);

        return redirect(route('home'));
    }

    public function payForm(Invoice $invoice)
    {
        return Inertia::render('invoices/PayForm', [
            'invoice' => $invoice,
            'idempotencyKey' => "invoice_pay:" . $invoice->id,
        ]);
    }

    public function send(Invoice $invoice)
    {
        if (!request()->has('email')) {
            return response()->json(['message' => __('Email is required.')], 422);
        }
        if ($invoice->sent_at) {
            return response()->json(['message' => __('Invoice already sent.')]);
        }
        SendInvoiceEmail::dispatch($invoice, request()->input('email'));
        return response()->json(['message' => __('Invoice will be sent in a few seconds.')]);
    }
}
