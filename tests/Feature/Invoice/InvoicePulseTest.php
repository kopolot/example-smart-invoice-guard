<?php

namespace Tests\Feature\Invoice;

use App\Models\Invoice;
use App\Models\User;
use App\Services\InvoicePulseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InvoicePulseTest extends TestCase
{
    use RefreshDatabase;

    private InvoicePulseService $pulse;

    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('redis')) {
            $this->markTestSkipped('The redis extension is required for Invoice Pulse tests.');
        }

        try {
            Redis::connection()->ping();
        } catch (\Throwable) {
            $this->markTestSkipped('Redis is not available for Invoice Pulse tests.');
        }

        // Other Feature tests may record pulse against reused SQLite IDs (e.g. 1).
        // Clear before each method so assertions start from a known empty DB 15.
        $this->flushPulseRedis();

        $this->pulse = app(InvoicePulseService::class);
    }

    protected function tearDown(): void
    {
        $this->flushPulseRedis();

        parent::tearDown();
    }

    private function flushPulseRedis(): void
    {
        try {
            Redis::flushdb();
        } catch (\Throwable) {
            // Ignore cleanup failures when Redis is unavailable.
        }
    }

    public function test_recording_a_visit_updates_set_hash_and_zset_atomically(): void
    {
        $user = User::factory()->create();
        $invoice = Invoice::factory()->create([
            'user_id' => $user->id,
            'number' => 'INV-PULSE-001',
        ]);

        $first = $this->pulse->record($invoice, 'guest:alpha');
        $second = $this->pulse->record($invoice, 'guest:alpha');
        $third = $this->pulse->record($invoice, 'guest:beta');

        $this->assertTrue($first['isNewVisitor']);
        $this->assertFalse($second['isNewVisitor']);
        $this->assertTrue($third['isNewVisitor']);
        $this->assertSame(3, $third['views']);
        $this->assertSame(2, $third['uniqueVisitors']);

        $stats = $this->pulse->forInvoice($invoice->id);

        $this->assertSame(3, $stats['views']);
        $this->assertSame(2, $stats['uniqueVisitors']);
        $this->assertSame('INV-PULSE-001', $stats['number']);
        $this->assertSame('guest:beta', $stats['lastVisitor']);

        $visitors = $this->pulse->visitors($invoice->id);
        sort($visitors);

        $this->assertSame(['guest:alpha', 'guest:beta'], $visitors);

        $hot = $this->pulse->hotForUser($user->id);

        $this->assertCount(1, $hot);
        $this->assertSame($invoice->id, $hot[0]['invoiceId']);
        $this->assertSame(3.0, $hot[0]['heat']);
        $this->assertSame(2, $hot[0]['uniqueVisitors']);
    }

    public function test_hot_invoices_are_ranked_by_heat_score(): void
    {
        $user = User::factory()->create();
        $cooler = Invoice::factory()->create([
            'user_id' => $user->id,
            'number' => 'INV-COOL',
        ]);
        $hotter = Invoice::factory()->create([
            'user_id' => $user->id,
            'number' => 'INV-HOT',
        ]);

        $this->pulse->record($cooler, 'guest:1');
        $this->pulse->record($hotter, 'guest:1');
        $this->pulse->record($hotter, 'guest:2');
        $this->pulse->record($hotter, 'guest:3');

        $hot = $this->pulse->hotForUser($user->id);

        $this->assertSame(['INV-HOT', 'INV-COOL'], array_column($hot, 'number'));
        $this->assertSame(3.0, $hot[0]['heat']);
        $this->assertSame(1.0, $hot[1]['heat']);
    }

    public function test_show_page_records_pulse_for_authenticated_owner(): void
    {
        $user = User::factory()->create();
        $invoice = Invoice::factory()->create([
            'user_id' => $user->id,
            'number' => 'INV-SHOW-001',
        ]);

        $this->actingAs($user)
            ->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('invoices/Show')
                    ->where('pulse.views', 1)
                    ->where('pulse.uniqueVisitors', 1)
                    ->where('pulse.number', 'INV-SHOW-001'),
            );

        $this->actingAs($user)
            ->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('pulse.views', 2)
                    ->where('pulse.uniqueVisitors', 1),
            );
    }

    public function test_public_pay_form_records_guest_pulse(): void
    {
        $invoice = Invoice::factory()->create([
            'user_id' => User::factory()->create()->id,
            'number' => 'INV-PAY-001',
        ]);

        $this->withSession([])
            ->get(route('invoices.show-pay', $invoice))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('invoices/PayForm')
                    ->where('pulse.views', 1)
                    ->where('pulse.uniqueVisitors', 1),
            );

        $stats = $this->pulse->forInvoice($invoice->id);

        $this->assertSame(1, $stats['views']);
        $this->assertNotNull($stats['lastVisitor']);
        $this->assertStringStartsWith('guest:', (string) $stats['lastVisitor']);
    }

    public function test_dashboard_includes_hot_invoices_from_redis(): void
    {
        $user = User::factory()->create();
        $invoice = Invoice::factory()->create([
            'user_id' => $user->id,
            'number' => 'INV-DASH-HOT',
        ]);

        $this->pulse->record($invoice, 'guest:dashboard');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Dashboard')
                    ->has('hotInvoices', 1)
                    ->where('hotInvoices.0.number', 'INV-DASH-HOT')
                    ->where('hotInvoices.0.views', 1)
                    ->where('hotInvoices.0.heat', 1),
            );
    }

    public function test_deleting_an_invoice_clears_pulse_keys(): void
    {
        $user = User::factory()->create();
        $invoice = Invoice::factory()->create([
            'user_id' => $user->id,
            'number' => 'INV-DELETE-PULSE',
        ]);

        $this->pulse->record($invoice, 'guest:gone');

        $this->assertNotSame([], Redis::hGetAll($this->pulse->statsKey($invoice->id)));
        $this->assertNotSame([], $this->pulse->hotForUser($user->id));

        $this->actingAs($user)
            ->delete(route('invoices.delete', $invoice))
            ->assertRedirect(route('invoices.index'));

        $this->assertSame([], Redis::hGetAll($this->pulse->statsKey($invoice->id)));
        $this->assertSame([], Redis::sMembers($this->pulse->visitorsKey($invoice->id)));
        $this->assertSame([], $this->pulse->hotForUser($user->id));
    }
}
