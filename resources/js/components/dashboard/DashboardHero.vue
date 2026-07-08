<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { create as createInvoice, index as invoiceIndex } from '@/routes/invoices';
import { Link } from '@inertiajs/vue3';
</script>

<template>
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
</template>
