/**
 * Stress API — push POST /api/invoice/generate past the Sanctum 60/min limiter.
 * Token-only (no session). Expect a mix of 200 and 429.
 *
 *   k6 run --insecure-skip-tls-verify tests/k6/stress-api.js
 */
import { sleep } from 'k6';
import { Counter } from 'k6/metrics';
import { thresholds } from './config.js';
import { assertBearerRaisesRateLimit, generateInvoicePdf } from './helpers/api.js';

const throttled = new Counter('api_pdf_throttled');
const succeeded = new Counter('api_pdf_succeeded');

export const options = {
    insecureSkipTLSVerify: true,
    scenarios: {
        stress: {
            executor: 'ramping-arrival-rate',
            startRate: 20,
            timeUnit: '1m',
            preAllocatedVUs: 5,
            maxVUs: 20,
            stages: [
                { duration: '30s', target: 60 },
                { duration: '1m', target: 120 },
                { duration: '30s', target: 30 },
            ],
        },
    },
    thresholds: thresholds.stressApi,
};

export function setup() {
    assertBearerRaisesRateLimit();
}

export default function () {
    const res = generateInvoicePdf({ expectThrottle: true });

    if (res.status === 429) {
        throttled.add(1);
    } else if (res.status === 200) {
        succeeded.add(1);
    }

    sleep(0.1);
}
