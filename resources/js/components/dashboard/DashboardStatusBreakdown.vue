<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cn } from '@/lib/utils';
import type { StatusBreakdownItem } from '@/types';

defineProps<{
    statusBreakdown: StatusBreakdownItem[];
}>();

const statusTone = (status: StatusBreakdownItem['status']) => {
    if (status === 'paid') {
        return 'bg-emerald-500';
    }

    if (status === 'partially_paid') {
        return 'bg-amber-500';
    }

    return 'bg-slate-500';
};
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Status breakdown</CardTitle>
            <CardDescription
                >Current invoice distribution by payment state.</CardDescription
            >
        </CardHeader>
        <CardContent class="space-y-3">
            <div
                v-for="item in statusBreakdown"
                :key="item.status"
                class="flex items-center justify-between rounded-lg border px-4 py-3"
            >
                <div class="flex items-center gap-3">
                    <span
                        class="h-2.5 w-2.5 rounded-full"
                        :class="cn(statusTone(item.status))"
                    />
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
</template>
