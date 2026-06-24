<script setup lang="ts">
import type { Invoice } from '@/types/invoice';
import { show as showInvoice } from '@/routes/invoices';


type Props = {
    invoice: Invoice;
};

defineProps<Props>();
</script>

<template>
    <div class="invoice-list-item">
        <a :href="showInvoice(invoice.id).url">
            <h3 class="text-lg font-bold">{{ invoice.number }}</h3>
            <p>{{ invoice.total_amount }}</p>
            <p class="text-sm text-gray-500">{{ invoice.status }}</p>
            <p class="text-sm text-gray-500">{{ invoice.date }}</p>
        </a>
    </div>
</template>

<style scoped>
    .invoice-list-item {
        display: flex;
        flex-direction: column;
        gap: 10px;
        border: 1px solid #e0e0e0;
        border-radius: 10px;
        padding: 10px;
        transition: all 0.3s ease;

    }
    .invoice-list-item a {
        text-decoration: none;
        color: inherit;
    }




</style>
