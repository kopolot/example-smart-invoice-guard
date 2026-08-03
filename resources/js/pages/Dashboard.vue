<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import DashboardHero from '@/components/dashboard/DashboardHero.vue';
import DashboardHotInvoices from '@/components/dashboard/DashboardHotInvoices.vue';
import DashboardMetricCards from '@/components/dashboard/DashboardMetricCards.vue';
import DashboardOverdueAlert from '@/components/dashboard/DashboardOverdueAlert.vue';
import DashboardQuickSummary from '@/components/dashboard/DashboardQuickSummary.vue';
import DashboardRecentActivity from '@/components/dashboard/DashboardRecentActivity.vue';
import DashboardRevenueChart from '@/components/dashboard/DashboardRevenueChart.vue';
import DashboardStatusBreakdown from '@/components/dashboard/DashboardStatusBreakdown.vue';
import { dashboard } from '@/routes';
import type {
    DashboardOverdue,
    DashboardSummary,
    HotInvoiceItem,
    MonthlyRevenueItem,
    RecentActivityItem,
    StatusBreakdownItem,
} from '@/types';

defineProps<{
    summary: DashboardSummary;
    overdue: DashboardOverdue;
    statusBreakdown: StatusBreakdownItem[];
    monthlyRevenue: MonthlyRevenueItem[];
    recentActivity: RecentActivityItem[];
    hotInvoices: HotInvoiceItem[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-1 flex-col gap-6 p-4">
        <DashboardHero />

        <DashboardOverdueAlert :overdue="overdue" />

        <DashboardMetricCards :summary="summary" />

        <section
            class="grid gap-4 xl:grid-cols-[minmax(0,2fr)_minmax(320px,1fr)]"
        >
            <DashboardRevenueChart :monthly-revenue="monthlyRevenue" />
            <DashboardStatusBreakdown :status-breakdown="statusBreakdown" />
        </section>

        <section
            class="grid gap-4 xl:grid-cols-[minmax(0,2fr)_minmax(280px,1fr)]"
        >
            <DashboardRecentActivity :recent-activity="recentActivity" />
            <DashboardQuickSummary :summary="summary" :overdue="overdue" />
        </section>

        <DashboardHotInvoices :hot-invoices="hotInvoices" />
    </div>
</template>
