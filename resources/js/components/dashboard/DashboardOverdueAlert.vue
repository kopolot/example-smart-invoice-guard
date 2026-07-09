<script setup lang="ts">
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { index } from '@/routes/invoices';
import type { DashboardOverdue } from '@/types';
import { TriangleAlert } from '@lucide/vue';

const props = defineProps<{
    overdue: DashboardOverdue;
}>();

const currencyFormatter = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    maximumFractionDigits: 2,
});
</script>

<template>
    <Card v-if="props.overdue.count > 0" class="border-amber-500/40 bg-amber-500/5">
        <CardHeader class="gap-3">
            <div class="flex items-center gap-2 text-amber-700 dark:text-amber-300">
                <TriangleAlert class="size-5" />
                <CardTitle class="text-lg">Overdue invoices need attention</CardTitle>
            </div>
            <CardDescription>
                {{ props.overdue.count }} invoice(s) passed their due date with
                {{ currencyFormatter.format(props.overdue.revenue) }} still outstanding.
            </CardDescription>
        </CardHeader>
        <CardContent>
            <a
                :href="index().url"
                class="text-sm font-medium text-amber-800 underline-offset-4 hover:underline dark:text-amber-200"
            >
                Review invoices
            </a>
        </CardContent>
    </Card>
</template>
