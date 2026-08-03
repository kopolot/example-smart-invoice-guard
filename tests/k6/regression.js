/**
 * Regression — CI gate over critical paths (home, auth browse, API PDF).
 *
 * Short shared-iterations run with per-endpoint latency budgets.
 * Threshold breaches exit non-zero so GitHub Actions fails the job.
 *
 * Local:
 *   docker compose exec -T php php artisan db:seed --class=K6Seeder --no-interaction
 *   export K6_API_TOKEN="$(docker compose exec -T php cat storage/app/private/k6/api-token.txt | tr -d '\r')"
 *   npm run test:k6:regression
 *
 * CI (compose profile k6, in-network URL):
 *   docker compose --profile k6 run --rm -T \
 *     -e K6_BASE_URL=https://nginx:8443 \
 *     -e K6_API_TOKEN="$TOKEN" \
 *     k6 run --insecure-skip-tls-verify regression.js
 */
import { sleep } from 'k6';
import { BASE_URL, apiToken, thresholds } from './config.js';
import { assertBearerRaisesRateLimit, generateInvoicePdf } from './helpers/api.js';
import { ensureSession, loginForSetup } from './helpers/auth.js';
import { browseAuthenticated, hitHome } from './helpers/web.js';

export const options = {
    insecureSkipTLSVerify: true,
    scenarios: {
        regression: {
            executor: 'shared-iterations',
            vus: Number(__ENV.K6_REGRESSION_VUS || 1),
            iterations: Number(__ENV.K6_REGRESSION_ITERS || 5),
            maxDuration: __ENV.K6_LOAD_DURATION || '2m',
        },
    },
    thresholds: thresholds.regression,
};

export function setup() {
    if (!apiToken) {
        throw new Error(
            'K6_API_TOKEN is required for regression. Seed with K6Seeder and export the token.',
        );
    }

    assertBearerRaisesRateLimit();

    return {
        baseUrl: BASE_URL,
        cookies: loginForSetup(),
    };
}

export default function (data) {
    ensureSession(data.cookies);
    hitHome();
    browseAuthenticated();
    generateInvoicePdf();
    sleep(0.5);
}
