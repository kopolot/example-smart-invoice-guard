/**
 * Smoke — quick health check of public + auth + API paths.
 *
 * Login runs once in setup() (Fortify: 5/min). VUs reuse that session.
 *
 *   docker compose exec -T php php artisan db:seed --class=K6Seeder --no-interaction
 *   export K6_API_TOKEN="$(docker compose exec -T php cat storage/app/private/k6/api-token.txt | tr -d '\r')"
 *   k6 run --insecure-skip-tls-verify tests/k6/smoke.js
 */
import { sleep } from 'k6';
import { BASE_URL, apiToken, thresholds } from './config.js';
import { assertBearerRaisesRateLimit, generateInvoicePdf } from './helpers/api.js';
import { ensureSession, loginForSetup } from './helpers/auth.js';
import { browseAuthenticated, hitHome } from './helpers/web.js';

export const options = {
    insecureSkipTLSVerify: true,
    scenarios: {
        smoke: {
            executor: 'shared-iterations',
            vus: Number(__ENV.K6_SMOKE_VUS || 1),
            iterations: Number(__ENV.K6_SMOKE_ITERS || 3),
            maxDuration: __ENV.K6_LOAD_DURATION || '1m',
        },
    },
    thresholds: thresholds.smoke,
};

export function setup() {
    if (!apiToken) {
        console.warn(
            'K6_API_TOKEN is empty — API calls count against the anonymous 5/min throttle. Run K6Seeder and export the token.',
        );
    } else {
        assertBearerRaisesRateLimit();
    }

    return {
        baseUrl: BASE_URL,
        cookies: loginForSetup(),
    };
}

export default function (data) {
    ensureSession(data.cookies);
    hitHome();
    browseAuthenticated();

    if (apiToken) {
        generateInvoicePdf();
    }

    sleep(1);
}
