<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;
use App\Services\DashboardStatsService;
use App\Services\OverdueInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_dashboard_displays_only_the_authenticated_users_metrics()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $paidInvoice = Invoice::factory()->create([
            'user_id' => $user->id,
            'number' => 'INV-PAID-001',
            'status' => InvoiceStatus::PAID,
            'total_amount' => 120,
            'date' => now()->subMonth(),
            'due_date' => now()->subWeek(),
            'sent_at' => now()->subDays(2),
        ]);

        $partiallyPaidInvoice = Invoice::factory()->create([
            'user_id' => $user->id,
            'number' => 'INV-PARTIAL-001',
            'status' => InvoiceStatus::PARTIALLY_PAID,
            'total_amount' => 80,
            'date' => now(),
            'due_date' => now()->addWeek(),
        ]);

        $unpaidInvoice = Invoice::factory()->create([
            'user_id' => $user->id,
            'number' => 'INV-OPEN-001',
            'status' => InvoiceStatus::UNPAID,
            'total_amount' => 50,
            'date' => now()->subMonths(2),
            'due_date' => now()->addMonth(),
        ]);

        Invoice::factory()->create([
            'user_id' => $otherUser->id,
            'number' => 'INV-FOREIGN-001',
            'status' => InvoiceStatus::PAID,
            'total_amount' => 999,
            'date' => now(),
            'sent_at' => now(),
        ]);

        $paidInvoice->statusHistories()->first()?->forceFill([
            'created_at' => now()->subHours(6),
            'updated_at' => now()->subHours(6),
        ])->save();

        $partiallyPaidInvoice->statusHistories()->first()?->forceFill([
            'created_at' => now()->subHours(3),
            'updated_at' => now()->subHours(3),
        ])->save();

        $unpaidInvoice->statusHistories()->first()?->forceFill([
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ])->save();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('summary.totalInvoices', 3)
                ->where('summary.collectedRevenue', 120)
                ->where('summary.outstandingRevenue', 130)
                ->where('summary.sentInvoices', 1)
                ->where('summary.averageInvoiceValue', 83.33)
                ->where('overdue.count', 0)
                ->where('overdue.revenue', 0)
                ->has('statusBreakdown', 4)
                ->where('statusBreakdown.0.status', 'paid')
                ->where('statusBreakdown.0.count', 1)
                ->where('statusBreakdown.1.status', 'unpaid')
                ->where('statusBreakdown.1.count', 1)
                ->where('statusBreakdown.2.status', 'partially_paid')
                ->where('statusBreakdown.2.count', 1)
                ->where('statusBreakdown.3.status', 'overdue')
                ->where('statusBreakdown.3.count', 0)
                ->has('monthlyRevenue', 7)
                ->has('recentActivity', 3)
                ->where('recentActivity.0.invoiceNumber', 'INV-OPEN-001')
                ->where('recentActivity.1.invoiceNumber', 'INV-PARTIAL-001')
                ->where('recentActivity.2.invoiceNumber', 'INV-PAID-001'),
            );
    }

    public function test_dashboard_stats_are_cached_until_invoice_changes(): void
    {
        $user = User::factory()->create();
        $invoice = Invoice::factory()->create([
            'user_id' => $user->id,
            'status' => InvoiceStatus::PAID,
            'total_amount' => 100,
            'date' => now(),
        ]);

        $service = app(DashboardStatsService::class);

        $this->assertSame(100.0, $service->forUser($user)['summary']['collectedRevenue']);
        $this->assertTrue(Cache::has($service->cacheKey($user->id)));

        DB::table('invoices')->where('id', $invoice->id)->update(['total_amount' => 500]);

        $this->assertSame(100.0, $service->forUser($user)['summary']['collectedRevenue']);

        $invoice->refresh()->update(['total_amount' => 250]);

        $this->assertFalse(Cache::has($service->cacheKey($user->id)));
        $this->assertSame(250.0, $service->forUser($user)['summary']['collectedRevenue']);
    }

    public function test_dashboard_stats_cache_rotates_when_the_day_changes(): void
    {
        $this->travelTo('2026-07-15 10:00:00');

        $user = User::factory()->create();
        $invoice = Invoice::factory()->create([
            'user_id' => $user->id,
            'status' => InvoiceStatus::PAID,
            'total_amount' => 100,
            'date' => now(),
        ]);

        $service = app(DashboardStatsService::class);
        $dayOneKey = $service->cacheKey($user->id);

        $this->assertSame(100.0, $service->forUser($user)['summary']['collectedRevenue']);
        $this->assertTrue(Cache::has($dayOneKey));

        DB::table('invoices')->where('id', $invoice->id)->update(['total_amount' => 400]);

        $this->travelTo('2026-07-16 10:00:00');

        $dayTwoKey = $service->cacheKey($user->id);

        $this->assertNotSame($dayOneKey, $dayTwoKey);
        $this->assertSame(400.0, $service->forUser($user)['summary']['collectedRevenue']);
        $this->assertTrue(Cache::has($dayTwoKey));
    }

    public function test_marking_invoices_overdue_invalidates_dashboard_cache(): void
    {
        $user = User::factory()->create();

        Invoice::factory()->create([
            'user_id' => $user->id,
            'status' => InvoiceStatus::UNPAID,
            'total_amount' => 75,
            'date' => now()->subMonth(),
            'due_date' => now()->subDay()->toDateString(),
        ]);

        $service = app(DashboardStatsService::class);

        $this->assertSame(0, $service->forUser($user)['overdue']['count']);
        $this->assertTrue(Cache::has($service->cacheKey($user->id)));

        app(OverdueInvoiceService::class)->markOverdue();

        $this->assertFalse(Cache::has($service->cacheKey($user->id)));
        $this->assertSame(1, $service->forUser($user)['overdue']['count']);
        $this->assertSame(75.0, $service->forUser($user)['overdue']['revenue']);
    }
}
