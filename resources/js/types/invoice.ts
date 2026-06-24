export type Invoice = {
    id: number;
    number: string;
    amount: number;
    tax_rate: number;
    total_amount: number;
    status: InvoiceStatus;
    date: string;
    user_id: number;
    pdf_path: string | null;
    created_at: string;
    updated_at: string;
    deleted_at: string | null;
};

export type InvoiceStatus = 'paid' | 'unpaid' | 'partially_paid';

export type InvoiceStatusesLabels = {
    [key in InvoiceStatus]: string;
}

export const availableStatusesLabels: InvoiceStatusesLabels = {
    paid: 'Paid',
    unpaid: 'Unpaid',
    partially_paid: 'Partially Paid',
};
