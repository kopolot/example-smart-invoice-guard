<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Invoice\StatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardStatsService
{
    /**
     * @return array{
     *     summary: array{
     *         totalInvoices: int,
     *         collectedRevenue: float,
     *         outstandingRevenue: float,
     *         sentInvoices: int,
     *         averageInvoiceValue: float
     *     },
     *     statusBreakdown: array<int, array{status: string, label: string, count: int}>,
     *     monthlyRevenue: array<int, array{month: string, label: string, revenue: float, invoices: int}>,
     *     recentActivity: array<int, array{invoiceNumber: string, status: string, changedAt: string}>
     * }
     */
    public function forUser(User $user): array
    {
        return Cache::remember(
            $this->cacheKey($user->id),
            now()->endOfDay(),
            fn (): array => $this->compute($user),
        );
    }

    public function forgetForUser(int $userId): void
    {
        Cache::forget($this->cacheKey($userId));
    }

    /**
     * @param  iterable<int>  $userIds
     */
    public function forgetForUsers(iterable $userIds): void
    {
        foreach (collect($userIds)->unique()->filter() as $userId) {
            $this->forgetForUser((int) $userId);
        }
    }

    public function cacheKey(int $userId): string
    {
        return sprintf('dashboard.stats.%d.%s', $userId, now()->toDateString());
    }

    /**
     * @return array{
     *     summary: array{
     *         totalInvoices: int,
     *         collectedRevenue: float,
     *         outstandingRevenue: float,
     *         sentInvoices: int,
     *         averageInvoiceValue: float
     *     },
     *     overdue: array{count: int, revenue: float},
     *     statusBreakdown: array<int, array{status: string, label: string, count: int}>,
     *     monthlyRevenue: array<int, array{month: string, label: string, revenue: float, invoices: int}>,
     *     recentActivity: array<int, array{invoiceNumber: string, status: string, changedAt: string}>
     * }
     */
    private function compute(User $user): array
    {
        return [
            'summary' => $this->summary($user),
            'overdue' => $this->overdue($user),
            'statusBreakdown' => $this->statusBreakdown($user),
            'monthlyRevenue' => $this->monthlyRevenue($user),
            'recentActivity' => $this->recentActivity($user),
        ];
    }

    /**
     * @return array{
     *     totalInvoices: int,
     *     collectedRevenue: float,
     *     outstandingRevenue: float,
     *     sentInvoices: int,
     *     averageInvoiceValue: float
     * }
     */
    private function summary(User $user): array
    {
        $summary = $user->invoices()
            ->selectRaw('count(*) as total_invoices')
            ->selectRaw(
                'coalesce(sum(case when status = ? then total_amount else 0 end), 0) as collected_revenue',
                [InvoiceStatus::PAID->value],
            )
            ->selectRaw(
                'coalesce(sum(case when status in (?, ?, ?) then total_amount else 0 end), 0) as outstanding_revenue',
                InvoiceStatus::openValues(),
            )
            ->selectRaw('count(case when sent_at is not null then 1 end) as sent_invoices')
            ->selectRaw('coalesce(avg(total_amount), 0) as average_invoice_value')
            ->first();

        return [
            'totalInvoices' => (int) ($summary?->total_invoices ?? 0),
            'collectedRevenue' => (float) ($summary?->collected_revenue ?? 0),
            'outstandingRevenue' => (float) ($summary?->outstanding_revenue ?? 0),
            'sentInvoices' => (int) ($summary?->sent_invoices ?? 0),
            'averageInvoiceValue' => round((float) ($summary?->average_invoice_value ?? 0), 2),
        ];
    }

    /**
     * @return array{count: int, revenue: float}
     */
    private function overdue(User $user): array
    {
        $overdue = $user->invoices()
            ->where('status', InvoiceStatus::OVERDUE)
            ->selectRaw('count(*) as overdue_count')
            ->selectRaw('coalesce(sum(total_amount), 0) as overdue_revenue')
            ->first();

        return [
            'count' => (int) ($overdue?->overdue_count ?? 0),
            'revenue' => round((float) ($overdue?->overdue_revenue ?? 0), 2),
        ];
    }

    /**
     * @return array<int, array{status: string, label: string, count: int}>
     */
    private function statusBreakdown(User $user): array
    {
        $counts = $user->invoices()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return collect(InvoiceStatus::cases())
            ->map(fn (InvoiceStatus $status): array => [
                'status' => $status->value,
                'label' => str($status->value)->replace('_', ' ')->title()->value(),
                'count' => (int) ($counts[$status->value] ?? 0),
            ])
            ->all();
    }

    /**
     * @return array<int, array{month: string, label: string, revenue: float, invoices: int}>
     */
    private function monthlyRevenue(User $user): array
    {
        $startMonth = now()->startOfMonth()->subMonths(5);
        $endMonth = now()->endOfMonth();
        $monthExpression = $this->monthExpression();

        $aggregates = $user->invoices()
            ->selectRaw("{$monthExpression} as month")
            ->selectRaw('coalesce(sum(total_amount), 0) as revenue')
            ->selectRaw('count(*) as invoices')
            ->whereBetween('date', [$startMonth->toDateString(), $endMonth->toDateTimeString()])
            ->groupBy(DB::raw($monthExpression))
            ->get()
            ->keyBy('month');

        return collect(range(5, 0, -1))
            ->map(fn (int $offset) => now()->startOfMonth()->subMonths($offset))
            ->push(now()->startOfMonth())
            ->map(fn ($month): array => [
                'month' => $month->format('Y-m'),
                'label' => $month->format('M'),
                'revenue' => round((float) ($aggregates->get($month->format('Y-m'))?->revenue ?? 0), 2),
                'invoices' => (int) ($aggregates->get($month->format('Y-m'))?->invoices ?? 0),
            ])
            ->all();
    }

    private function monthExpression(): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', date)",
            default => "date_format(date, '%Y-%m')",
        };
    }

    /**
     * @return array<int, array{invoiceNumber: string, status: string, changedAt: string}>
     */
    private function recentActivity(User $user): array
    {
        return StatusHistory::query()
            ->select('invoice_status_histories.status', 'invoice_status_histories.created_at', 'invoices.number as invoice_number')
            ->join('invoices', 'invoices.id', '=', 'invoice_status_histories.invoice_id')
            ->where('invoices.user_id', $user->id)
            ->latest('invoice_status_histories.created_at')
            ->limit(8)
            ->get()
            ->map(fn (StatusHistory $history): array => [
                'invoiceNumber' => $history->invoice_number,
                'status' => str($history->status)->replace('_', ' ')->title()->value(),
                'changedAt' => $history->created_at->toISOString(),
            ])
            ->all();
    }
}
