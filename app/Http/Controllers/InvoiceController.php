<?php

namespace App\Http\Controllers;

use App\Events\InvoicePaid;
use App\Http\Requests\SearchInvoicesRequest;
use App\Http\Requests\SendInvoiceRequest;
use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\UpdateInvoiceRequest;
use App\Jobs\GenerateInvoicePdfJob;
use App\Jobs\SendInvoiceEmail;
use App\Models\Invoice;
use App\Services\InvoicePriceCalculator;
use App\Services\InvoicePulseService;
use App\Services\InvoiceSearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class InvoiceController extends Controller
{
    public function __construct(
        private InvoicePriceCalculator $invoicePriceCalculator,
        private InvoicePulseService $invoicePulseService,
        private InvoiceSearchService $invoiceSearchService,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(SearchInvoicesRequest $request)
    {
        $perPage = 10;
        $page = (int) $request->input('page', 1);
        $query = $request->searchQuery();

        if ($query !== '') {
            $invoices = $this->invoiceSearchService->ping()
                ? $this->invoiceSearchService->search(
                    (int) $request->user()->id,
                    $query,
                    $perPage,
                    $page,
                )
                : $request->user()->invoices()
                    ->where(function ($builder) use ($query): void {
                        $builder
                            ->where('number', 'like', '%'.$query.'%')
                            ->orWhere('status', 'like', '%'.$query.'%');
                    })
                    ->paginate($perPage, page: $page);
        } else {
            $invoices = $request->user()->invoices()->paginate($perPage, page: $page);
        }

        // if is ajax request and no inertia request, return the invoices
        if ($request->ajax() && ! $request->inertia()) {
            return response()->json([
                'invoicesPagination' => $invoices,
                'searchQuery' => $query,
            ]);
        }

        return Inertia::render('invoices/Index', [
            'invoicesPagination' => $invoices,
            'searchQuery' => $query,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('invoices/Create', ['idempotencyKey' => Str::uuid()]);
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
            'due_date' => $request->due_date,
        ]);

        return redirect()->route('invoices.show', $invoice);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Invoice $invoice)
    {
        $this->invoicePulseService->recordFromRequest($invoice, $request);

        return Inertia::render('invoices/Show', [
            'invoice' => $invoice,
            'pulse' => $this->invoicePulseService->forInvoice($invoice->id),
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
            'due_date' => $request->due_date,
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

    public function showPayForm(Request $request, Invoice $invoice)
    {
        $this->invoicePulseService->recordFromRequest($invoice, $request);

        return Inertia::render('invoices/PayForm', [
            'invoice' => $invoice,
            'pulse' => $this->invoicePulseService->forInvoice($invoice->id),
        ]);
    }

    public function send(SendInvoiceRequest $request, Invoice $invoice)
    {
        if ($invoice->sent_at) {
            return response()->json(['message' => __('Invoice already sent.')]);
        }

        SendInvoiceEmail::dispatch($invoice, $request->validated('email'));

        return response()->json(['message' => __('Invoice will be sent in a few seconds.')]);
    }
}
