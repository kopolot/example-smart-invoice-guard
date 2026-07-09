<x-mail::message>
# Invoice overdue

Invoice **{{ $invoice->number }}** is past its due date of **{{ $invoice->due_date }}**.

Outstanding amount: **{{ number_format((float) $invoice->total_amount, 2) }}**

<x-mail::button :url="route('invoices.show', $invoice)">
View invoice
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
