<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;
use App\Notifications\InvoiceOverdueReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ProcessOverdueInvoicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_marks_unpaid_invoices_past_due_date_as_overdue(): void
    {
        $user = User::factory()->create();

        $overdueInvoice = Invoice::factory()->create([
            'user_id' => $user->id,
            'status' => InvoiceStatus::UNPAID,
            'due_date' => now()->subDay()->toDateString(),
        ]);

        $openInvoice = Invoice::factory()->create([
            'user_id' => $user->id,
            'status' => InvoiceStatus::UNPAID,
            'due_date' => now()->addWeek()->toDateString(),
        ]);

        Artisan::call('invoices:process-overdue');

        $this->assertSame(InvoiceStatus::OVERDUE, $overdueInvoice->fresh()->status);
        $this->assertSame(InvoiceStatus::UNPAID, $openInvoice->fresh()->status);
    }

    public function test_command_does_not_mark_paid_invoices_as_overdue(): void
    {
        $user = User::factory()->create();

        $paidInvoice = Invoice::factory()->create([
            'user_id' => $user->id,
            'status' => InvoiceStatus::PAID,
            'due_date' => now()->subWeek()->toDateString(),
        ]);

        Artisan::call('invoices:process-overdue');

        $this->assertSame(InvoiceStatus::PAID, $paidInvoice->fresh()->status);
    }

    public function test_command_sends_overdue_reminder_to_invoice_owner(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        Invoice::factory()->create([
            'user_id' => $user->id,
            'status' => InvoiceStatus::OVERDUE,
            'due_date' => now()->subDays(3)->toDateString(),
        ]);

        Artisan::call('invoices:process-overdue');

        Notification::assertSentTo($user, InvoiceOverdueReminder::class);
    }

    public function test_command_does_not_resend_overdue_reminder(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        Invoice::factory()->create([
            'user_id' => $user->id,
            'status' => InvoiceStatus::OVERDUE,
            'due_date' => now()->subDays(3)->toDateString(),
            'overdue_reminded_at' => now()->subDay(),
        ]);

        Artisan::call('invoices:process-overdue');

        Notification::assertNothingSent();
    }

    public function test_marking_overdue_creates_status_history_entry(): void
    {
        $user = User::factory()->create();

        $invoice = Invoice::factory()->create([
            'user_id' => $user->id,
            'status' => InvoiceStatus::PARTIALLY_PAID,
            'due_date' => now()->subDays(2)->toDateString(),
        ]);

        Artisan::call('invoices:process-overdue');

        $this->assertDatabaseHas('invoice_status_histories', [
            'invoice_id' => $invoice->id,
            'status' => InvoiceStatus::OVERDUE->value,
        ]);
    }
}
