import { check } from 'k6';
import http from 'k6/http';
import { BASE_URL, apiToken } from '../config.js';
import { idempotencyKey, invoicePdfPayload } from './invoice.js';

function rateLimitHeaders(res) {
    return {
        limit: Number(res.headers['X-Ratelimit-Limit'] || res.headers['X-RateLimit-Limit'] || 0),
        remaining: Number(
            res.headers['X-Ratelimit-Remaining'] || res.headers['X-RateLimit-Remaining'] || -1,
        ),
    };
}

/**
 * POST /api/invoice/generate
 *
 * Authenticated limit is 60/min; anonymous is 5/min.
 */
export function generateInvoicePdf(options = {}) {
    const { expectThrottle = false } = options;

    const params = {
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Idempotency-Key': idempotencyKey(),
        },
        tags: { endpoint: 'api_pdf' },
    };

    if (apiToken) {
        params.headers.Authorization = `Bearer ${apiToken}`;
    }

    const res = http.post(
        `${BASE_URL}/api/invoice/generate`,
        JSON.stringify(invoicePdfPayload()),
        params,
    );

    if (expectThrottle) {
        check(res, {
            'api pdf 200 or 429': (r) => r.status === 200 || r.status === 429,
        });
    } else {
        const ok = check(res, {
            'api pdf status 200': (r) => r.status === 200,
            'api pdf has pdf_url': (r) => {
                try {
                    return typeof r.json('pdf_url') === 'string';
                } catch {
                    return false;
                }
            },
        });

        if (!ok && (typeof __ITER === 'undefined' || __ITER < 3)) {
            const { limit, remaining } = rateLimitHeaders(res);
            console.error(
                `api pdf failed status=${res.status} rate-limit=${limit} remaining=${remaining} body=${String(res.body).slice(0, 160)}`,
            );
        }
    }

    return res;
}

/**
 * Fail fast if token is missing/stale OR the 60/min bucket is already nearly empty.
 */
export function assertBearerRaisesRateLimit() {
    if (!apiToken) {
        throw new Error(
            'K6_API_TOKEN is empty. Run: php artisan db:seed --class=K6Seeder && export K6_API_TOKEN=...',
        );
    }

    const res = http.post(
        `${BASE_URL}/api/invoice/generate`,
        JSON.stringify(invoicePdfPayload()),
        {
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Idempotency-Key': idempotencyKey(),
                Authorization: `Bearer ${apiToken}`,
            },
            tags: { endpoint: 'api_pdf_setup' },
        },
    );

    const { limit, remaining } = rateLimitHeaders(res);

    if (res.status === 429 || (res.status === 200 && remaining >= 0 && remaining < 40)) {
        throw new Error(
            `invoice-api bucket is exhausted (status=${res.status}, limit=${limit}, remaining=${remaining}). ` +
                'Clear limiter then retry: docker compose exec php php artisan cache:clear',
        );
    }

    if (res.status !== 200 || limit < 60) {
        throw new Error(
            `K6_API_TOKEN is not accepted by Sanctum (status=${res.status}, rate-limit=${limit || 'n/a'}). ` +
                'Re-seed and re-export: php artisan db:seed --class=K6Seeder && ' +
                "export K6_API_TOKEN=\"$(docker compose exec -T php cat storage/app/private/k6/api-token.txt | tr -d '\\r')\"",
        );
    }

    console.log(`API token OK (limit=${limit}, remaining=${remaining})`);
}
