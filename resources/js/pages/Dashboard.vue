<script setup lang="ts">
import DashboardHero from '@/components/dashboard/DashboardHero.vue';
import DashboardMetricCards from '@/components/dashboard/DashboardMetricCards.vue';
import DashboardQuickSummary from '@/components/dashboard/DashboardQuickSummary.vue';
import DashboardRecentActivity from '@/components/dashboard/DashboardRecentActivity.vue';
import DashboardRevenueChart from '@/components/dashboard/DashboardRevenueChart.vue';
import DashboardStatusBreakdown from '@/components/dashboard/DashboardStatusBreakdown.vue';
import { dashboard } from '@/routes';
import type { DashboardSummary, MonthlyRevenueItem, RecentActivityItem, StatusBreakdownItem } from '@/types';
import { Head } from '@inertiajs/vue3';

const props = defineProps<{
    summary: DashboardSummary;
    statusBreakdown: StatusBreakdownItem[];
    monthlyRevenue: MonthlyRevenueItem[];
    recentActivity: RecentActivityItem[];
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

        <DashboardMetricCards :summary="summary" />

        <section class="grid gap-4 xl:grid-cols-[minmax(0,2fr)_minmax(320px,1fr)]">
            <DashboardRevenueChart :monthly-revenue="monthlyRevenue" />
            <DashboardStatusBreakdown :status-breakdown="statusBreakdown" />
        </section>

        <section class="grid gap-4 xl:grid-cols-[minmax(0,2fr)_minmax(280px,1fr)]">
            <DashboardRecentActivity :recent-activity="recentActivity" />
            <DashboardQuickSummary :summary="summary" />
        </section>
    </div>
</template>
