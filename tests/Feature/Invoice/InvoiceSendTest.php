<?php

namespace Tests\Feature\Invoice;

use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Jobs\SendInvoiceEmail;
use App\Models\User;
use App\Jobs\GenerateInvoicePdfJob;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use App\Mail\InvoiceSentMail;


class InvoiceSendTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic feature test example.
     */
    public function test_example(): void
    {
        Storage::fake('public');
        Mail::fake();
        User::factory()->create();
        $invoice = Invoice::factory()->create();
        GenerateInvoicePdfJob::dispatch($invoice);
        SendInvoiceEmail::dispatch($invoice->refresh(), 'test@example.com');
        $invoice->refresh();
        $this->assertNotNull($invoice->pdf_path);
        $this->assertNotNull($invoice->sent_at);
        Mail::assertSent(InvoiceSentMail::class);
    }
}
