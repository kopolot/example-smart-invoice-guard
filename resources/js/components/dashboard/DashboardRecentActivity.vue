<script setup lang="ts">
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { RecentActivityItem } from '@/types';

defineProps<{
    recentActivity: RecentActivityItem[];
}>();

const formatActivityDate = (value: string) =>
    new Intl.DateTimeFormat('en-US', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Recent activity</CardTitle>
            <CardDescription
                >Latest recorded status changes from your
                invoices.</CardDescription
            >
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
                        {{ formatActivityDate(item.changedAt) }}
                    </p>
                </div>
            </div>
            <div
                v-else
                class="rounded-lg border border-dashed px-4 py-10 text-center text-sm text-muted-foreground"
            >
                No activity yet. Create or update an invoice to start building
                your timeline.
            </div>
        </CardContent>
    </Card>
</template>
