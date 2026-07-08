<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { create as createInvoice, index as invoiceIndex } from '@/routes/invoices';
import { dashboard } from '@/routes';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

type DashboardSummary = {
    totalInvoices: number;
    collectedRevenue: number;
    outstandingRevenue: number;
    sentInvoices: number;
    averageInvoiceValue: number;
};

type StatusBreakdownItem = {
    status: 'paid' | 'unpaid' | 'partially_paid';
    label: string;
    count: number;
};

type MonthlyRevenueItem = {
    month: string;
    label: string;
    revenue: number;
    invoices: number;
};

type RecentActivityItem = {
    invoiceNumber: string;
    status: string;
    changedAt: string;
};

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

const currencyFormatter = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    maximumFractionDigits: 2,
});

const integerFormatter = new Intl.NumberFormat('en-US');

const metricCards = computed(() => [
    {
        label: 'Total invoices',
        value: integerFormatter.format(props.summary.totalInvoices),
        hint: 'All active invoices in your workspace',
    },
    {
        label: 'Collected revenue',
        value: currencyFormatter.format(props.summary.collectedRevenue),
        hint: 'Value of invoices already marked as paid',
    },
    {
        label: 'Outstanding revenue',
        value: currencyFormatter.format(props.summary.outstandingRevenue),
        hint: 'Open value across unpaid and partially paid invoices',
    },
    {
        label: 'Average invoice',
        value: currencyFormatter.format(props.summary.averageInvoiceValue),
        hint: `${integerFormatter.format(props.summary.sentInvoices)} invoices already sent to customers`,
    },
]);

const maxMonthlyRevenue = computed(() => Math.max(...props.monthlyRevenue.map((item) => item.revenue), 1));

const chartItems = computed(() =>
    props.monthlyRevenue.map((item) => ({
        ...item,
        revenueLabel: currencyFormatter.format(item.revenue),
        height: `${Math.max((item.revenue / maxMonthlyRevenue.value) * 100, item.revenue > 0 ? 12 : 4)}%`,
    })),
);

const statusTone = (status: StatusBreakdownItem['status']) => {
    if (status === 'paid') {
        return 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300';
    }

    if (status === 'partially_paid') {
        return 'bg-amber-500/15 text-amber-700 dark:text-amber-300';
    }

    return 'bg-slate-500/15 text-slate-700 dark:text-slate-300';
};

const formatRelativeDate = (value: string) =>
    new Intl.DateTimeFormat('en-US', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-1 flex-col gap-6 p-4">
        <section class="flex flex-col gap-4 rounded-xl border bg-card p-6 shadow-sm lg:flex-row lg:items-end lg:justify-between">
            <div class="space-y-2">
                <Badge variant="outline">Business overview</Badge>
                <div class="space-y-1">
                    <h1 class="text-2xl font-semibold tracking-tight">Invoice performance at a glance</h1>
                    <p class="max-w-2xl text-sm text-muted-foreground">
                        Track collection, open balance, monthly trend, and the latest invoice state changes without leaving the dashboard.
                    </p>
                </div>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row">
                <Link
                    :href="invoiceIndex()"
                    class="inline-flex h-9 items-center justify-center rounded-md border px-4 py-2 text-sm font-medium shadow-xs transition-colors hover:bg-accent hover:text-accent-foreground"
                >
                    View invoices
                </Link>
                <Link
                    :href="createInvoice()"
                    class="inline-flex h-9 items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                >
                    Create invoice
                </Link>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <Card v-for="card in metricCards" :key="card.label">
                <CardHeader class="gap-3">
                    <CardDescription>{{ card.label }}</CardDescription>
                    <CardTitle class="text-3xl">{{ card.value }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-sm text-muted-foreground">
                        {{ card.hint }}
                    </p>
                </CardContent>
            </Card>
        </section>

        <section class="grid gap-4 xl:grid-cols-[minmax(0,2fr)_minmax(320px,1fr)]">
            <Card>
                <CardHeader>
                    <CardTitle>Revenue trend</CardTitle>
                    <CardDescription>Last 6 full months plus current month based on invoice dates.</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid min-h-72 grid-cols-6 items-end gap-3 sm:grid-cols-7">
                        <div
                            v-for="item in chartItems"
                            :key="item.month"
                            class="flex h-full flex-col justify-end gap-3"
                        >
                            <div class="text-center text-xs font-medium text-muted-foreground">
                                {{ item.revenueLabel }}
                            </div>
                            <div class="flex h-56 items-end">
                                <div
                                    class="w-full rounded-t-md bg-primary/85 transition-all"
                                    :style="{ height: item.height }"
                                />
                            </div>
                            <div class="space-y-1 text-center">
                                <p class="text-sm font-medium">{{ item.label }}</p>
                                <p class="text-xs text-muted-foreground">
                                    {{ item.invoices }} invoices
                                </p>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Status breakdown</CardTitle>
                    <CardDescription>Current invoice distribution by payment state.</CardDescription>
                </CardHeader>
                <CardContent class="space-y-3">
                    <div
                        v-for="item in statusBreakdown"
                        :key="item.status"
                        class="flex items-center justify-between rounded-lg border px-4 py-3"
                    >
                        <div class="flex items-center gap-3">
                            <span class="h-2.5 w-2.5 rounded-full" :class="statusTone(item.status)" />
                            <div>
                                <p class="font-medium">{{ item.label }}</p>
                                <p class="text-sm text-muted-foreground">
                                    {{ item.count }} invoices
                                </p>
                            </div>
                        </div>
                        <Badge variant="secondary">{{ item.count }}</Badge>
                    </div>
                </CardContent>
            </Card>
        </section>

        <section class="grid gap-4 xl:grid-cols-[minmax(0,2fr)_minmax(280px,1fr)]">
            <Card>
                <CardHeader>
                    <CardTitle>Recent activity</CardTitle>
                    <CardDescription>Latest recorded status changes from your invoices.</CardDescription>
                </CardHeader>
                <CardContent>
                    <div v-if="recentActivity.length" class="space-y-3">
                        <div
                            v-for="item in recentActivity"
                            :key="`${item.invoiceNumber}-${item.changedAt}-${item.status}`"
                            class="flex flex-col justify-between gap-3 rounded-lg border px-4 py-3 sm:flex-row sm:items-center"
                        >
                            <div>
                                <p class="font-medium">{{ item.invoiceNumber }}</p>
                                <p class="text-sm text-muted-foreground">
                                    Status changed to {{ item.status }}
                                </p>
                            </div>
                            <p class="text-sm text-muted-foreground">
                                {{ formatRelativeDate(item.changedAt) }}
                            </p>
                        </div>
                    </div>
                    <div v-else class="rounded-lg border border-dashed px-4 py-10 text-center text-sm text-muted-foreground">
                        No activity yet. Create or update an invoice to start building your timeline.
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Quick reading</CardTitle>
                    <CardDescription>A short summary you can turn into next actions.</CardDescription>
                </CardHeader>
                <CardContent class="space-y-4 text-sm text-muted-foreground">
                    <p>
                        You currently have
                        <span class="font-medium text-foreground"> {{ summary.totalInvoices }} </span>
                        invoices in play, including
                        <span class="font-medium text-foreground"> {{ summary.sentInvoices }} </span>
                        already sent to customers.
                    </p>
                    <p>
                        Paid invoices account for
                        <span class="font-medium text-foreground"> {{ currencyFormatter.format(summary.collectedRevenue) }} </span>,
                        while open balance still totals
                        <span class="font-medium text-foreground"> {{ currencyFormatter.format(summary.outstandingRevenue) }} </span>.
                    </p>
                    <p>
                        Use this view as the base for the next portfolio step: overdue reminders, partial payment history, or realtime updates after payment events.
                    </p>
                </CardContent>
            </Card>
        </section>
    </div>
</template>
