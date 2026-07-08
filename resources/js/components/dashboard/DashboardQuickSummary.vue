<script setup lang="ts">
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import type { DashboardSummary } from '@/types';

const props = defineProps<{
    summary: DashboardSummary;
}>();

const currencyFormatter = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    maximumFractionDigits: 2,
});
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Quick reading</CardTitle>
            <CardDescription>A short summary you can turn into next actions.</CardDescription>
        </CardHeader>
        <CardContent class="space-y-4 text-sm text-muted-foreground">
            <p>
                You currently have
                <span class="font-medium text-foreground"> {{ props.summary.totalInvoices }} </span>
                invoices in play, including
                <span class="font-medium text-foreground"> {{ props.summary.sentInvoices }} </span>
                already sent to customers.
            </p>
            <p>
                Paid invoices account for
                <span class="font-medium text-foreground"> {{ currencyFormatter.format(props.summary.collectedRevenue) }} </span>,
                while open balance still totals
                <span class="font-medium text-foreground"> {{ currencyFormatter.format(props.summary.outstandingRevenue) }} </span>.
            </p>
            <p>
                Use this view as the base for the next portfolio step: overdue reminders, partial payment history, or realtime updates after payment events.
            </p>
        </CardContent>
    </Card>
</template>
