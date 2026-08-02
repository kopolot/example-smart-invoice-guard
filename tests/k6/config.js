/**
 * Shared k6 configuration for Smart Invoice Guard.
 *
 * Env:
 *   K6_BASE_URL         default https://localhost:8443
 *   K6_EMAIL            default k6@example.com
 *   K6_PASSWORD         default password
 *   K6_API_TOKEN        Sanctum bearer token (from K6Seeder)
 *   K6_SMOKE_VUS        smoke VUs (do NOT use K6_VUS — k6 builtin override)
 *   K6_SMOKE_ITERS      smoke iterations (do NOT use K6_ITERATIONS)
 *   K6_LOAD_DURATION    load/api duration (do NOT use K6_DURATION)
 *   K6_API_RATE         API arrivals per minute in load.js
 */
export const BASE_URL = (__ENV.K6_BASE_URL || 'https://localhost:8443').replace(/\/$/, '');

export const credentials = {
    email: __ENV.K6_EMAIL || 'k6@example.com',
    password: __ENV.K6_PASSWORD || 'password',
};

export const apiToken = __ENV.K6_API_TOKEN || '';

export const thresholds = {
    smoke: {
        http_req_failed: ['rate<0.05'],
        http_req_duration: ['p(95)<2000'],
        checks: ['rate>0.95'],
    },
    load: {
        http_req_failed: ['rate<0.05'],
        http_req_duration: ['p(95)<1500', 'p(99)<3000'],
        checks: ['rate>0.95'],
        // PDF generation is heavier — tracked separately
        'http_req_duration{endpoint:api_pdf}': ['p(95)<5000'],
        'http_req_duration{endpoint:dashboard}': ['p(95)<1000'],
        'http_req_duration{endpoint:invoices}': ['p(95)<1200'],
        'http_req_duration{endpoint:invoice_search}': ['p(95)<1500'],
        'http_req_duration{endpoint:home}': ['p(95)<800'],
    },
    stressApi: {
        // Expect some 429s when pushing past invoice-api throttle
        http_req_failed: ['rate<0.4'],
        http_req_duration: ['p(95)<8000'],
        checks: ['rate>0.6'],
        'http_req_duration{endpoint:api_pdf}': ['p(95)<8000'],
    },
};
