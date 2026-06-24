<script setup lang="ts">
import { index, show, edit, deleteMethod } from '@/routes/invoices';
import type { Invoice } from '@/types/invoice';
import { availableStatusesLabels } from '@/types/invoice';

defineProps<{
    invoice: Invoice;
}>();

defineOptions({
    layout: (props: { invoice: Invoice }) => ({
        breadcrumbs: [
            {
                title: 'Invoices',
                href: index(),
            },
            {
                title: props.invoice.number,
                href: show(props.invoice.id),
            },
        ],
    }),
});
</script>

<template>
    <Head :title="`Invoice ${invoice.number}`" />
    <h1 class="sr-only">Invoice {{ invoice.number }}</h1>
    <div class="grid grid-cols-2 gap-4 p-5">
        <div class="flex flex-row border-b border-gray-200 pb-4 gap-2">
            <Label>Number</Label>
            <div class="">
                <p>{{ invoice.number }}</p>
            </div>
        </div>
        <div class="flex flex-row border-b border-gray-200 pb-4 gap-2">
                <Label>Amount</Label>
                <div class="">
                    <p>{{ invoice.amount }}</p>
                </div>
        </div>
        <div class="flex flex-row border-b border-gray-200 pb-4 gap-2">
            <Label>Tax Rate</Label>
            <div class="">
                    <p>{{ invoice.tax_rate }}</p>
                </div>
        </div>
        <div class="flex flex-row border-b border-gray-200 pb-4 gap-2">
            <Label>Total Amount</Label>
            <div class="">
                <p>{{ invoice.total_amount }}</p>
            </div>
        </div>
        <div class="flex flex-row border-b border-gray-200 pb-4 gap-2">
            <Label>Status</Label>
            <div class="">
                <p>{{ availableStatusesLabels[invoice.status] }}</p>
            </div>
        </div>
        <div class="flex flex-row border-b border-gray-200 pb-4 gap-2">
            <Label>Date</Label>
            <div class="">
                <p>{{ invoice.date }}</p>
            </div>
        </div>
    </div>
    <div class="p-5 flex flex-row gap-2">
        <Button class="bg-blue-500 text-white px-5 py-1 rounded" >
            <a :href="edit(invoice.id).url">Edit</a>
        </Button>
        <Button class="bg-red-500 text-white px-5 py-1 rounded" >
            <a :href="deleteMethod(invoice.id).url">Delete</a>
        </Button>
        <Button class="bg-green-500 text-white px-5 py-1 rounded" >
            <a href="#">Set as Paid</a>
        </Button>
    </div>
</template>

<style scoped>
.grid {
    display: grid;
    grid-template-columns: repeat(1, 1fr);
    gap: 1rem;
}
</style>
