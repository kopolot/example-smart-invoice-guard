<x-mail::message>
    # Invoice Sent

    Your invoice has been sent to your email address.

    You can view your invoice

    <x-mail::button :url="$invoice->pdf_url">
        View Invoice
    </x-mail::button>

    Thanks,
    {{ config('app.name') }}
</x-mail::message>