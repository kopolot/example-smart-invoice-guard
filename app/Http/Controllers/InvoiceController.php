<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\UpdateInvoiceRequest;
use App\Models\Invoice;
use App\Services\InvoicePriceCalculator;
use Inertia\Inertia;

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
        return Inertia::render('invoices/Index', [
            'invoices' => auth()->user()->invoices,
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
}
