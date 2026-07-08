<script setup lang="ts">
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import type { MonthlyRevenueItem } from '@/types';
import { computed } from 'vue';

const props = defineProps<{
    monthlyRevenue: MonthlyRevenueItem[];
}>();

const currencyFormatter = new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    maximumFractionDigits: 2,
});

const maxMonthlyRevenue = computed(() => Math.max(...props.monthlyRevenue.map((item) => item.revenue), 1));

const chartItems = computed(() =>
    props.monthlyRevenue.map((item) => ({
        ...item,
        revenueLabel: currencyFormatter.format(item.revenue),
        height: `${Math.max((item.revenue / maxMonthlyRevenue.value) * 100, item.revenue > 0 ? 12 : 4)}%`,
    })),
);
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Revenue trend</CardTitle>
            <CardDescription>Last 6 full months plus current month based on invoice dates.</CardDescription>
        </CardHeader>
        <CardContent>
            <div class="grid min-h-72 grid-cols-6 items-end gap-3 sm:grid-cols-7">
                <div v-for="item in chartItems" :key="item.month" class="flex h-full flex-col justify-end gap-3">
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
</template>
