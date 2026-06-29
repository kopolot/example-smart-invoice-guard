<?php

namespace Tests\Feature\Invoice;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class InvoicePayTest extends TestCase
{
    use RefreshDatabase;

    private function createUnpaidInvoice(): Invoice
    {
        $user = User::factory()->create();

        return Invoice::factory()->create([
            'user_id' => $user->id,
            'status' => InvoiceStatus::UNPAID,
        ]);
    }

    public function test_guests_can_view_pay_form(): void
    {
        $this->withoutVite();

        $invoice = $this->createUnpaidInvoice();

        $response = $this->get(route('invoices.pay.form', $invoice));

        $response->assertOk();
        $response->assertInertia(
            fn($page) => $page
                ->component('invoices/PayForm')
                ->has('idempotencyKey')
        );
    }

    public function test_pay_requires_idempotency_key(): void
    {
        $invoice = $this->createUnpaidInvoice();

        $response = $this->from(route('invoices.pay.form', $invoice))
            ->patch(route('invoices.pay', $invoice), []);

        $response->assertUnprocessable()
            ->assertJsonPath('error', __('Idempotency key is required.'));
    }

    public function test_guests_can_pay_invoice_with_idempotency_key(): void
    {
        $invoice = $this->createUnpaidInvoice();

        $idempotencyKey = "invoice_pay:" . $invoice->id;

        $response = $this->patch(route('invoices.pay', $invoice), [], [
            'X-Idempotency-Key' => $idempotencyKey,
        ]);

        $response->assertRedirect(route('home'));
        $this->assertSame(InvoiceStatus::PAID, $invoice->fresh()->status);
        $this->assertTrue(Cache::has("idempotency_key:{$idempotencyKey}"));
    }

    public function test_duplicate_pay_request_with_same_key_replays_success(): void
    {
        $invoice = $this->createUnpaidInvoice();

        $idempotencyKey = "invoice_pay:" . $invoice->id;
        $headers = ['X-Idempotency-Key' => $idempotencyKey];

        $this->patch(route('invoices.pay', $invoice), [], $headers);
        $response = $this->from(route('invoices.pay.form', $invoice))
            ->patch(route('invoices.pay', $invoice), [], $headers);

        $response->assertRedirect(route('home'));
    }

    public function test_paid_invoice_cannot_be_paid_again(): void
    {
        $user = User::factory()->create();
        $invoice = Invoice::factory()->create([
            'user_id' => $user->id,
            'status' => InvoiceStatus::PAID,
        ]);

        $response = $this->patch(route('invoices.pay', $invoice), [], [
            'X-Idempotency-Key' => (string) Str::uuid(),
        ]);

        $response->assertRedirect(route('invoices.show', $invoice));
        $response->assertSessionHas('inertia.flash_data', fn(array $flash) => $flash['toast'] === [
            'type' => 'error',
            'message' => 'Invoice already paid.',
        ]);
    }

    public function test_invoice_owner_can_pay_via_authenticated_request(): void
    {
        $user = User::factory()->create();
        $invoice = Invoice::factory()->create([
            'user_id' => $user->id,
            'status' => InvoiceStatus::UNPAID,
        ]);

        $response = $this->actingAs($user)->patch(route('invoices.pay', $invoice), [], [
            'X-Idempotency-Key' => "invoice_pay:" . $invoice->id,
        ]);

        $response->assertRedirect(route('home'));
        $this->assertSame(InvoiceStatus::PAID, $invoice->fresh()->status);
    }
}
