<x-mail::message>
    # Invoice Paid

    Your invoice #{{ $invoice->number }} has been paid.

    Invoice Number: {{ $invoice->number }}
    Amount: {{ $invoice->amount }}<br>
    Status: {{ $invoice->status }}<br>

    Thanks,
    {{ config('app.name') }}
</x-mail::message>