<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { index, show, edit, deleteMethod, pdf, pay, send } from '@/routes/invoices';
import type { Invoice } from '@/types/invoice';
import { availableStatusesLabels } from '@/types/invoice';
import { Form , useForm} from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import axios from 'axios';
import { ref } from 'vue';
import { toast } from 'vue-sonner';


const props = defineProps<{
    invoice: any;
}>();

console.log(props.invoice);

const invoice = ref<Invoice>(props.invoice);

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

useEcho(
    `App.Models.User.${invoice.value.user_id}`,
    'InvoicePdfGenerated',
    (event) => {
        invoice.value.pdf_path = event.pdf_path;
        invoice.value.pdf_url = event.pdf_url;
    }
)

useEcho(
    `App.Models.User.${invoice.value.user_id}`,
    'InvoiceSent',
    (event) => {
        invoice.value.sent_at = event.sent_at;
    }
)

const sendForm = useForm({
    email: '',
});

const copyPaymentLink = () => {
    const paymentLink = new URL(pay(invoice.value.id).url, window.location.origin);
    navigator.clipboard.writeText(paymentLink.toString());
    toast.success('Payment link copied to clipboard');
};

const generatePdf = async () => {
    try {
        const uri = pdf(invoice.value.id).url;
        const url = new URL(uri, window.location.origin);
        const response = await axios.get(url.toString())
        toast.success(response.data.message);
    } catch (error) {
        console.error(error);
        toast.error('Failed to generate PDF');
    }
};

const sendInvoice = async () => {
    try {
        const uri = send(invoice.value.id).url;
        const url = new URL(uri, window.location.origin);
        const response = await axios.patch(
            url.toString(),
            sendForm.data()
        )
        toast.success(response.data.message);
    } catch (error) {
        console.error(error);
        toast.error('Failed to send invoice');
    }
};
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
            <Label>Tax Number</Label>
            <div class="">
                <p>{{ invoice.tax_number }}</p>
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
    <div class="p-5 flex flex-row gap-2 flex-wrap">
        <Button class="bg-blue-500 text-white px-5 py-1 rounded" >
            <a :href="edit(invoice.id).url">Edit</a>
        </Button>
        <Button class="bg-red-500 text-white px-5 py-1 rounded" >
            <Form :action="deleteMethod(invoice.id).url" method="delete" v-slot="{   processing }">
                <Button type="submit" :disabled="processing">Delete</Button>
            </Form>
        </Button>
        <Button class="bg-green-500 text-white px-5 py-1 rounded cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed" @click="copyPaymentLink" :disabled="invoice.status === 'paid'" >
            {{ invoice.status === 'paid' ? 'Invoice already paid' : 'Copy payment link' }}
        </Button>
        <Button class="bg-yellow-500 text-white px-5 py-1 rounded cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed" :disabled="invoice.pdf_path !== null && invoice.pdf_path !== ''" @click="generatePdf" >
            Generate PDF
        </Button>
        <div class="flex flex-row gap-2 w-100" v-if="invoice.pdf_path !== null && invoice.pdf_path !== ''">
            <form  @submit.prevent="sendInvoice" class="flex flex-row gap-2 flex-wrap">
                <Input required v-model="sendForm.email" :disabled="invoice.sent_at !== null || sendForm.processing" type="email" name="email" placeholder="Email" />
                <InputError :message="sendForm.errors.email" />
                <Button
                    :disabled="invoice.sent_at !== null || sendForm.processing"
                    class="bg-purple-500 text-white px-5 py-1 rounded cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    Send Invoice
                </Button>
            </form>
        </div>
    </div>
    <div class="p-5" v-if="invoice.pdf_url">
        <Label>PDF</Label>
        <div class="flex flex-row border-b border-gray-200 pb-4 gap-2">
            <iframe :src="invoice.pdf_url" frameborder="0" class="w-full h-full min-h-[500px]"/>
        </div>
        <a class="text-blue-500" :href="invoice.pdf_url" target="_blank">Download PDF</a>
    </div>
</template>

<style scoped>
.grid {
    display: grid;
    grid-template-columns: repeat(1, 1fr);
    gap: 1rem;
}
</style>
