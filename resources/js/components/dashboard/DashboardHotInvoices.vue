<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { show } from '@/routes/invoices';
import type { HotInvoiceItem } from '@/types';

defineProps<{
    hotInvoices: HotInvoiceItem[];
}>();

const formatSeenAt = (value: string | null) => {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat('en-US', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
};
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle>Hot invoices</CardTitle>
            <CardDescription>
                Live attention ranking from Redis (unique visitors, view heat).
            </CardDescription>
        </CardHeader>
        <CardContent>
            <div v-if="hotInvoices.length" class="space-y-3">
                <div
                    v-for="item in hotInvoices"
                    :key="item.invoiceId"
                    class="flex flex-col justify-between gap-3 rounded-lg border px-4 py-3 sm:flex-row sm:items-center"
                >
                    <div>
                        <Link
                            :href="show(item.invoiceId)"
                            class="font-medium text-foreground underline-offset-4 hover:underline"
                        >
                            {{ item.number }}
                        </Link>
                        <p class="text-sm text-muted-foreground">
                            {{ item.views }} views ·
                            {{ item.uniqueVisitors }} unique · heat
                            {{ item.heat }}
                        </p>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        {{ formatSeenAt(item.lastSeenAt) }}
                    </p>
                </div>
            </div>
            <div
                v-else
                class="rounded-lg border border-dashed px-4 py-10 text-center text-sm text-muted-foreground"
            >
                No pulse yet. Open an invoice or share a payment link to start
                ranking attention.
            </div>
        </CardContent>
    </Card>
</template>
