<script setup lang="ts">
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { DashboardOverdue, DashboardSummary } from '@/types';

const props = defineProps<{
    summary: DashboardSummary;
    overdue: DashboardOverdue;
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
            <CardDescription
                >A short summary you can turn into next
                actions.</CardDescription
            >
        </CardHeader>
        <CardContent class="space-y-4 text-sm text-muted-foreground">
            <p>
                You currently have
                <span class="font-medium text-foreground">
                    {{ props.summary.totalInvoices }}
                </span>
                invoices in play, including
                <span class="font-medium text-foreground">
                    {{ props.summary.sentInvoices }}
                </span>
                already sent to customers.
            </p>
            <p>
                Paid invoices account for
                <span class="font-medium text-foreground">
                    {{
                        currencyFormatter.format(props.summary.collectedRevenue)
                    }} </span
                >, while open balance still totals
                <span class="font-medium text-foreground">
                    {{
                        currencyFormatter.format(
                            props.summary.outstandingRevenue,
                        )
                    }} </span
                >.
            </p>
            <p v-if="props.overdue.count > 0">
                <span class="font-medium text-foreground">
                    {{ props.overdue.count }}
                </span>
                invoice(s) are overdue, representing
                <span class="font-medium text-foreground">
                    {{ currencyFormatter.format(props.overdue.revenue) }}
                </span>
                that needs follow-up.
            </p>
            <p v-else>
                No invoices are currently overdue. Scheduled reminders will
                notify you when due dates are missed.
            </p>
        </CardContent>
    </Card>
</template>
