<?php

namespace Tests\Feature\Invoice;

use App\Jobs\GenerateInvoicePdfJob;
use App\Jobs\SendInvoiceEmail;
use App\Mail\InvoiceSentMail;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InvoiceSendTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_can_be_sent_by_email(): void
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

    public function test_send_dispatches_job_with_valid_email(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $invoice = Invoice::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->patchJson(route('invoices.send', $invoice), [
            'email' => 'client@example.com',
        ]);

        $response->assertOk();
        Queue::assertPushed(SendInvoiceEmail::class);
    }

    public function test_send_requires_valid_email(): void
    {
        $user = User::factory()->create();
        $invoice = Invoice::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->from(route('invoices.show', $invoice))
            ->patch(route('invoices.send', $invoice), [
                'email' => 'not-an-email',
            ]);

        $response->assertRedirect(route('invoices.show', $invoice));
        $response->assertSessionHasErrors('email');
    }

    public function test_send_requires_email_field(): void
    {
        $user = User::factory()->create();
        $invoice = Invoice::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->from(route('invoices.show', $invoice))
            ->patch(route('invoices.send', $invoice), []);

        $response->assertRedirect(route('invoices.show', $invoice));
        $response->assertSessionHasErrors('email');
    }
}
