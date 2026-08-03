<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import { TailwindPagination } from 'laravel-vue-pagination';
import { ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import InvoiceListItem from '@/components/invoices/ListItem.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { index } from '@/routes/invoices';
import { create as createInvoice } from '@/routes/invoices';

const props = defineProps<{
    invoicesPagination: any;
    searchQuery: string;
}>();

const invoices = ref<any>(props.invoicesPagination);
const search = ref(props.searchQuery ?? '');

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

watch(
    () => props.invoicesPagination,
    (value) => {
        invoices.value = value;
    },
);

watch(
    () => props.searchQuery,
    (value) => {
        search.value = value ?? '';
    },
);

const submitSearch = () => {
    router.get(
        index.url({
            query: {
                q: search.value || undefined,
            },
        }),
        {},
        {
            preserveState: true,
            replace: true,
        },
    );
};

const getPaginationData = async (page = 1) => {
    try {
        const response = await axios.get(index().url, {
            params: {
                page: page,
                q: search.value || undefined,
            },
        });
        invoices.value = response.data.invoicesPagination;
        router.visit(response.request.responseURL);
    } catch (error) {
        console.error(error);
        toast.error('Failed to fetch invoices');
    }
};
</script>

<template>
    <Head title="Invoices" />
    <div
        class="flex w-full flex-col items-start justify-start gap-4 p-4 sm:flex-row sm:items-center sm:justify-between"
    >
        <Button>
            <a :href="createInvoice().url">Create Invoice</a>
        </Button>

        <form class="flex w-full max-w-md gap-2" @submit.prevent="submitSearch">
            <Input
                v-model="search"
                type="search"
                name="q"
                placeholder="Search invoices (Elasticsearch)…"
                class="w-full"
                autocomplete="off"
            />
            <Button type="submit" variant="secondary">Search</Button>
        </form>
    </div>

    <p v-if="searchQuery" class="px-4 text-sm text-muted-foreground">
        Showing Elasticsearch results for “{{ searchQuery }}”.
    </p>

    <div class="grid grid-cols-1 gap-4 p-4 md:grid-cols-2 lg:grid-cols-5">
        <div v-for="invoice in invoices.data" :key="invoice.id">
            <InvoiceListItem :invoice="invoice" />
        </div>
    </div>

    <div
        v-if="!invoices.data?.length"
        class="px-4 pb-6 text-center text-sm text-muted-foreground"
    >
        {{
            searchQuery
                ? 'No invoices matched your search.'
                : 'No invoices yet.'
        }}
    </div>

    <div class="flex items-center justify-center">
        <TailwindPagination
            :limit="3"
            :data="invoices"
            @pagination-change-page="getPaginationData"
        />
    </div>
</template>
