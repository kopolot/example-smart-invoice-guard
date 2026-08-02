<?php

namespace Tests\Unit\Events;

use App\Events\InvoicePdfGenerated;
use App\Events\InvoiceSent;
use App\Jobs\GenerateInvoicePdfJob;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InvoiceBroadcastEventsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function invoice_pdf_generated_broadcasts_synchronously(): void
    {
        $user = User::factory()->create();
        $invoice = Invoice::factory()->create([
            'user_id' => $user->id,
            'pdf_path' => 'invoices/1/test.pdf',
        ]);

        $event = new InvoicePdfGenerated($invoice);

        $this->assertInstanceOf(ShouldBroadcastNow::class, $event);
        $this->assertSame(
            ['pdf_path' => 'invoices/1/test.pdf', 'pdf_url' => $invoice->pdf_url],
            $event->broadcastWith()
        );
    }

    #[Test]
    public function invoice_sent_broadcasts_synchronously_with_sent_at(): void
    {
        $user = User::factory()->create();
        $invoice = Invoice::factory()->create([
            'user_id' => $user->id,
            'sent_at' => now(),
        ]);

        $event = new InvoiceSent($invoice);

        $this->assertInstanceOf(ShouldBroadcastNow::class, $event);
        $this->assertSame(
            ['sent_at' => $invoice->sent_at?->toIso8601String()],
            $event->broadcastWith()
        );
    }

    #[Test]
    public function generate_pdf_job_dispatches_broadcast_event(): void
    {
        Storage::fake('public');
        Event::fake([InvoicePdfGenerated::class]);

        $user = User::factory()->create();
        $invoice = Invoice::factory()->create(['user_id' => $user->id]);

        GenerateInvoicePdfJob::dispatchSync($invoice);

        Event::assertDispatched(InvoicePdfGenerated::class);
        $this->assertNotNull($invoice->fresh()->pdf_path);
    }
}
