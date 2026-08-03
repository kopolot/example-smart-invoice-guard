<script setup lang="ts">
import { computed } from 'vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { DashboardSummary } from '@/types';

const props = defineProps<{
    summary: DashboardSummary;
}>();

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
</script>

<template>
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
</template>
