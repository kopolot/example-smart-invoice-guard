<script setup lang="ts">
import { create as createInvoice } from '@/routes/invoices';
import InvoiceListItem from '@/components/invoices/ListItem.vue';
import { index } from '@/routes/invoices';
import type { Invoice } from '@/types/invoice';
import { Button } from '@/components/ui/button';
import { router } from '@inertiajs/vue3';
defineProps<{
    invoices: Invoice[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Invoices',
                href: index(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Invoices" />
    <div class="flex flex-col space-y-6 justify-start items-start p-4">
        <Button class="">
            <a :href="createInvoice().url">Create Invoice</a>
        </Button>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 p-4">
        <div v-for="invoice in invoices" :key="invoice.id">
            <InvoiceListItem :invoice="invoice" />
        </div>
    </div>
</template>
