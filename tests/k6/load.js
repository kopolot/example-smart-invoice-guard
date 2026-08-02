/**
 * Load — mixed traffic with isolated concerns:
 *   web_browse  → one Fortify session (shared), hits dashboard/invoices/search
 *   api_pdf     → Sanctum Bearer only (no session), stays under 60/min with token
 *
 *   export K6_API_TOKEN="$(docker compose exec -T php cat storage/app/private/k6/api-token.txt | tr -d '\r')"
 *   k6 run --insecure-skip-tls-verify tests/k6/load.js
 */
import { sleep } from 'k6';
import { thresholds } from './config.js';
import { assertBearerRaisesRateLimit, generateInvoicePdf } from './helpers/api.js';
import { ensureSession, loginForSetup } from './helpers/auth.js';
import { browseAuthenticated, hitHome } from './helpers/web.js';

export const options = {
    insecureSkipTLSVerify: true,
    scenarios: {
        web_browse: {
            executor: 'ramping-vus',
            startVUs: 0,
            stages: [
                { duration: '30s', target: 5 },
                { duration: '1m', target: 10 },
                { duration: '30s', target: 0 },
            ],
            gracefulRampDown: '10s',
            exec: 'webBrowse',
        },
        api_pdf: {
            executor: 'constant-arrival-rate',
            // Stay under Sanctum authenticated limit (60/min). Empty the bucket first: php artisan cache:clear
            rate: Number(__ENV.K6_API_RATE || 45),
            timeUnit: '1m',
            duration: __ENV.K6_LOAD_DURATION || '2m',
            preAllocatedVUs: 3,
            maxVUs: 8,
            exec: 'apiPdf',
            startTime: '5s',
        },
    },
    thresholds: thresholds.load,
};

export function setup() {
    assertBearerRaisesRateLimit();

    return { cookies: loginForSetup() };
}

export function webBrowse(data) {
    ensureSession(data.cookies);

    if (__ITER % 5 === 0) {
        hitHome();
    }

    browseAuthenticated();
    sleep(1 + Math.floor(Math.random() * 3));
}

export function apiPdf() {
    generateInvoicePdf();
}
