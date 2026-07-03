<?php

namespace Tests\Feature\Invoice;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_invoice_to_number_used_by_another_user(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $invoiceA = Invoice::factory()->create([
            'user_id' => $userA->id,
            'number' => 'INV-A-001',
            'status' => InvoiceStatus::UNPAID,
        ]);

        Invoice::factory()->create([
            'user_id' => $userB->id,
            'number' => 'INV-B-001',
            'status' => InvoiceStatus::UNPAID,
        ]);

        $response = $this->actingAs($userA)->put(route('invoices.update', $invoiceA), [
            'number' => 'INV-B-001',
            'amount' => $invoiceA->amount,
            'tax_rate' => $invoiceA->tax_rate,
            'tax_number' => '1234567890',
            'date' => $invoiceA->date,
            'status' => $invoiceA->status->value,
        ]);

        $response->assertRedirect(route('invoices.show', $invoiceA));
        $this->assertSame('INV-B-001', $invoiceA->fresh()->number);
    }

    public function test_user_cannot_update_invoice_to_duplicate_number_within_own_invoices(): void
    {
        $user = User::factory()->create();

        Invoice::factory()->create([
            'user_id' => $user->id,
            'number' => 'INV-EXISTING',
            'status' => InvoiceStatus::UNPAID,
        ]);

        $invoice = Invoice::factory()->create([
            'user_id' => $user->id,
            'number' => 'INV-OTHER',
            'status' => InvoiceStatus::UNPAID,
        ]);

        $response = $this->actingAs($user)->put(route('invoices.update', $invoice), [
            'number' => 'INV-EXISTING',
            'amount' => $invoice->amount,
            'tax_rate' => $invoice->tax_rate,
            'tax_number' => '1234567890',
            'date' => $invoice->date,
            'status' => $invoice->status->value,
        ]);

        $response->assertSessionHasErrors('number');
        $this->assertSame('INV-OTHER', $invoice->fresh()->number);
    }
}
