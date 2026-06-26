<script setup lang="ts">
import InvoiceListItem from '@/components/invoices/ListItem.vue';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/invoices';
import { create as createInvoice } from '@/routes/invoices';
// import type { Invoice } from '@/types/invoice';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { TailwindPagination } from 'laravel-vue-pagination';
import { ref } from 'vue';
import { toast } from 'vue-sonner';

const props = defineProps<{
    invoicesPagination: any;
}>();

const invoices = ref<any>(props.invoicesPagination);

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

const getPaginationData = async (page = 1) => {
    try {
        const response = await axios.get(index().url, {
            params: {
                page: page,
            },
        })
        invoices.value = response.data.invoicesPagination;
        router.visit(response.request.responseURL);
    }catch(error){
        console.error(error);
        toast.error('Failed to fetch invoices');
    }
};
</script>

<template>
    <Head title="Invoices" />
    <div class="flex flex-col space-y-6 justify-start items-start p-4">
        <Button class="">
            <a :href="createInvoice().url">Create Invoice</a>
        </Button>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 p-4">
        <div v-for="invoice in invoices.data" :key="invoice.id">
            <InvoiceListItem :invoice="invoice" />

        </div>

    </div>
    <div class="flex justify-center items-center">
        <!-- pagination -->
        <TailwindPagination :limit="3" :data="invoices"  @pagination-change-page="getPaginationData"/>
    </div>
</template>
