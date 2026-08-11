import { check, sleep } from 'k6';
import { SharedArray } from 'k6/data';
import http from 'k6/http';
import { Counter, Trend } from 'k6/metrics';
import { BASE_URL } from './config.js';
import { idempotencyKey, invoicePdfPayload } from './helpers/invoice.js';

const products = new SharedArray('products', function () {
    return JSON.parse(open('./data/product-ids.json'));
});

const tokens = new SharedArray('api_tokens', function () {
    return JSON.parse(open('./data/api-tokens.json'));
});

const errorCount = new Counter('demo_errors');
const apiPdfTrend = new Trend('api_pdf_duration', true);

export const options = {
    insecureSkipTLSVerify: true,
    discardResponseBodies: true,
    summaryTrendStats: ['avg', 'min', 'med', 'p(90)', 'p(95)', 'p(99)', 'max'],
    scenarios: {
        invoice_pdf_generation: {
            executor: 'ramping-arrival-rate',
            startRate: 2,
            timeUnit: '1s',
            preAllocatedVUs: 30,
            maxVUs: 80,
            gracefulStop: '30s',
            stages: [
                { duration: '20s', target: 5 },
                { duration: '30s', target: 15 },
                { duration: '30s', target: 55 },
                { duration: '20s', target: 5 },
            ],
        },
    },
    thresholds: {
        http_req_failed: ['rate<0.05'],
        checks: ['rate>0.95'],
        api_pdf_duration: ['p(95)<5000', 'p(99)<8000'],
        'http_req_duration{endpoint:api_pdf}': ['p(95)<5000', 'p(99)<8000'],
    },
};

export function setup() {
    if (!tokens.length) {
        throw new Error(
            'Token pool empty. Run: docker compose exec php php artisan db:seed --class=K6Seeder --no-interaction',
        );
    }

    console.log(`Products: ${products.length}, tokens: ${tokens.length}, base: ${BASE_URL}`);

    return {
        productCount: products.length,
        tokenCount: tokens.length,
    };
}

export default function () {
    const product = products[Math.floor(Math.random() * products.length)];
    const auth = tokens[Math.floor(Math.random() * tokens.length)];

    const payload = invoicePdfPayload({
        number: `K6-${product.id}-${Date.now()}-${__VU}-${__ITER}`,
        amount: 50 + product.id.length * 10,
    });

    const res = http.post(`${BASE_URL}/api/invoice/generate`, JSON.stringify(payload), {
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Idempotency-Key': idempotencyKey(),
            Authorization: `Bearer ${auth.token}`,
        },
        tags: {
            endpoint: 'api_pdf',
            product_id: product.id,
        },
        responseType: 'text',
    });

    apiPdfTrend.add(res.timings.duration);

    const ok = check(res, {
        'status 200': (r) => r.status === 200,
        'content-type json': (r) =>
            (r.headers['Content-Type'] || '').includes('application/json'),
    });

    if (!ok) {
        errorCount.add(1);

        if (errorCount.value <= 5) {
            console.error(
                `[VU ${__VU} iter ${__ITER}] user=${auth.email} product=${product.id} ` +
                    `status=${res.status} duration=${res.timings.duration.toFixed(0)}ms ` +
                    `body=${String(res.body).slice(0, 120)}`,
            );
        }
    }

    sleep(0.1);
}

export function teardown(data) {
    console.log(`Done. Products: ${data.productCount}, tokens: ${data.tokenCount}`);
}
