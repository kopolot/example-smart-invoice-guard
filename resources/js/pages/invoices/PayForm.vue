<script setup lang="ts">
import { pay } from '@/actions/App/Http/Controllers/InvoiceController';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import type { Invoice } from '@/types/invoice';
import { Form, Head } from '@inertiajs/vue3';

defineOptions({
    layout: (props: { invoice: Invoice }) => ({
        title: `Pay Invoice ${props.invoice.number}`,
        breadcrumbs: [
            {
                title: 'Invoices',
            },
            {
                title: `Pay Invoice ${props.invoice.number}`,
            },
        ],
    }),
});

const props = defineProps<{
    invoice: Invoice;
    pulse?: {
        views: number;
        uniqueVisitors: number;
        lastSeenAt: string | null;
        lastVisitor: string | null;
        number: string | null;
    };
}>();
</script>

<template>
    <Head title="Pay Invoice" />
    <h1 class="sr-only">Pay Invoice simulation</h1>
    <div class="flex flex-col space-y-6 p-4">
        <h2 class="text-2xl font-bold">Pay Invoice simulation</h2>
        <p v-if="props.pulse" class="text-sm text-muted-foreground">
            {{ props.pulse.views }} views · {{ props.pulse.uniqueVisitors }} unique visitors
        </p>
        <Form
            v-bind="pay.form(props.invoice.id)"
            v-slot="{ processing }"
        >
            <div class="space-y-6 p-10">
                <Button class="w-full" type="submit" :disabled="processing">
                    <Spinner v-if="processing" />
                    Pay
                </Button>
            </div>
        </Form>
    </div>
</template>
