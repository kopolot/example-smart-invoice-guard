<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { edit, update, index, show } from '@/routes/invoices';
import type { Invoice } from '@/types/invoice';
import { availableStatusesLabels } from '@/types/invoice';
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';


const props = defineProps<{
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
                title: 'Show Invoice',
                href: show(props.invoice.id),
            },
            {
                title: props.invoice.number,
                href: edit(props.invoice.id),
            },
        ],
    }),
});

const invoiceRef = ref<Invoice>(props.invoice);

</script>

<template>
    <Head :title="`Edit Invoice ${invoice.number}`" />
    <div class="flex flex-col space-y-6 p-4">
        <h1>Edit Invoice {{ invoice.number }}</h1>
        <Form class="grid grid-cols-2 gap-2" :action="update(invoice.id).url" v-slot="{ errors, processing }" method="post">
            <div class="row grid grid-cols-3">
                <Label class="col" for="number">Number</Label>
                <div class="col-2 col-end-4">
                    <Input required type="text" name="number" placeholder="Number" v-model="invoiceRef.number" />
                    <InputError :message="errors.number" />
                </div>
            </div>
            <div class="row grid grid-cols-3">
                <Label class="col" for="amount">Amount</Label>
                <div class="col-2 col-end-4">
                    <Input required type="number" name="amount" placeholder="Amount" v-model="invoiceRef.amount" />
                    <InputError :message="errors.amount" />
                </div>
            </div>
            <div class="row grid grid-cols-3">
                <Label class="col" for="tax_rate">Tax Rate</Label>
                <div class="col-2 col-end-4">
                    <Input required type="number" name="tax_rate" placeholder="Tax Rate" v-model="invoiceRef.tax_rate" />
                    <InputError :message="errors.tax_rate" />
                </div>
            </div>
            <div class="row grid grid-cols-3">
                <Label class="col" for="tax_number">Tax Number</Label>
                <div class="col-2 col-end-4">
                    <Input required type="text" name="tax_number" placeholder="Tax Number" v-model="invoiceRef.tax_number" />
                    <InputError :message="errors.tax_number" />
                </div>
            </div>
            <div class="row grid grid-cols-3">
                <Label class="col" for="status">Status</Label>
                <div class="col-2 col-end-4">
                    <Select required name="status" v-model="invoiceRef.status">
                        <SelectTrigger class="w-100">
                            <SelectValue placeholder="Select a status">
                                {{ availableStatusesLabels[invoiceRef.status]}}
                            </SelectValue>
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="label,status of availableStatusesLabels" :value="status" :key="status">{{ label }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.status" />
                </div>
            </div>
            <div class="row grid grid-cols-3">
                <Label class="col" for="date">Date</Label>
                <div class="col-2 col-end-4">
                    <Input required type="date" name="date" placeholder="Date" v-model="invoiceRef.date" />
                    <InputError :message="errors.date" />
                </div>
            </div>
            <Button class="col-1 col-end-2" type="submit" :disabled="processing">Update Invoice</Button>
            <Button class="col-2 col-end-3">
                <a :href="show(invoice.id).url">
                    Back to Invoice
                </a>
            </Button>
        </Form>
    </div>
</template>

<style scoped>

</style>

