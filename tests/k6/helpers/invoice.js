import { uuidv4 } from './uuid.js';

/** Payload accepted by POST /api/invoice/generate. */
export function invoicePdfPayload(overrides = {}) {
    const today = new Date();
    const due = new Date(today);
    due.setDate(due.getDate() + 14);

    const ymd = (d) => d.toISOString().slice(0, 10);
    const vu = typeof __VU !== 'undefined' ? __VU : 0;
    const iter = typeof __ITER !== 'undefined' ? __ITER : 0;

    return {
        number: `K6-${Date.now()}-${vu}-${iter}`,
        amount: 100 + (vu % 50),
        tax_rate: 0.23,
        tax_number: '5250000000',
        date: ymd(today),
        due_date: ymd(due),
        status: 'unpaid',
        ...overrides,
    };
}

export function idempotencyKey() {
    return uuidv4();
}
