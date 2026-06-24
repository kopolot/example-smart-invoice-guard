<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { store, create, index } from '@/routes/invoices';
import { availableStatusesLabels } from '@/types/invoice';
import { Form, Head } from '@inertiajs/vue3';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Invoices',
                href: index(),
            },
            {
                title: 'Create Invoice',
                href: create(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Create Invoice" />
    <div class="flex flex-col space-y-6 p-4">
        <h1>Create Invoice</h1>
        <Form class="grid grid-cols-2 gap-2" :action="store.post().url" v-slot="{ errors, processing }" method="post">
            <div class="row grid grid-cols-3">
                <Label class="col" for="number">Number</Label>
                <div class="col-2 col-end-4">
                    <Input required type="text" name="number" placeholder="Number" />
                    <InputError :message="errors.number" />
                </div>
            </div>
            <div class="row grid grid-cols-3">
                <Label class="col" for="amount">Amount</Label>
                <div class="col-2 col-end-4">
                    <Input required type="number" name="amount" placeholder="Amount" />
                    <InputError :message="errors.amount" />
                </div>
            </div>
            <div class="row grid grid-cols-3">
                <Label class="col" for="tax_rate">Tax Rate</Label>
                <div class="col-2 col-end-4">
                    <Input required type="number" name="tax_rate" placeholder="Tax Rate" />
                    <InputError :message="errors.tax_rate" />
                </div>
            </div>
            <div class="row grid grid-cols-3">
                <Label class="col" for="status">Status</Label>
                <div class="col-2 col-end-4">
                    <Select required name="status">
                        <SelectTrigger class="w-100">
                            <SelectValue placeholder="Select a status" />
                        </SelectTrigger>
                        <SelectContent >
                            <SelectItem v-for="label,status of availableStatusesLabels" :value="status" :key="status">{{ label }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.status" />
                </div>
            </div>
            <div class="row grid grid-cols-3">
                <Label class="col" for="date">Date</Label>
                <div class="col-2 col-end-4">
                    <Input required type="date" name="date" placeholder="Date" />
                    <InputError :message="errors.date" />
                </div>
            </div>
            <Button class="col-1 col-end-2" type="submit" :disabled="processing">Create Invoice</Button>
        </Form>
    </div>
</template>

<style scoped>

</style>

