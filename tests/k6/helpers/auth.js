import { check } from 'k6';
import http from 'k6/http';
import { BASE_URL, credentials } from '../config.js';

/**
 * Read Laravel XSRF-TOKEN from the VU cookie jar and decode it for the header.
 */
export function xsrfToken() {
    const jar = http.cookieJar();
    const cookies = jar.cookiesForURL(BASE_URL);
    const values = cookies['XSRF-TOKEN'];

    if (!values || !values.length) {
        return '';
    }

    return decodeURIComponent(values[0]);
}

/** Plain browser-like headers (avoid X-Inertia → 409 without version handshake). */
export function pageHeaders(extra = {}) {
    return {
        Accept: 'text/html, application/xhtml+xml',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': xsrfToken(),
        ...extra,
    };
}

/** Snapshot auth cookies after a successful login (for setup → VU handoff). */
export function exportCookies() {
    const jar = http.cookieJar();
    const cookies = jar.cookiesForURL(BASE_URL);
    const exported = {};

    for (const [name, values] of Object.entries(cookies)) {
        if (values && values.length) {
            exported[name] = values[0];
        }
    }

    return exported;
}

/** Install cookies from setup() into this VU's jar. */
export function importCookies(exported) {
    if (!exported) {
        return;
    }

    const jar = http.cookieJar();

    for (const [name, value] of Object.entries(exported)) {
        // Secure + path must match Laravel's Set-Cookie or the jar drops them on HTTPS.
        jar.set(BASE_URL, name, value, {
            path: '/',
            secure: true,
        });
    }
}

/**
 * Fortify login — keep rare (5/min per email|IP).
 */
export function login() {
    const loginPage = http.get(`${BASE_URL}/login`, {
        headers: { Accept: 'text/html' },
        tags: { endpoint: 'login_page' },
    });

    check(loginPage, {
        'login page status 200': (r) => r.status === 200,
        'login page has csrf cookie': () => xsrfToken() !== '',
    });

    const res = http.post(
        `${BASE_URL}/login`,
        JSON.stringify({
            email: credentials.email,
            password: credentials.password,
        }),
        {
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': xsrfToken(),
            },
            tags: { endpoint: 'login' },
            redirects: 0,
        },
    );

    const ok = check(res, {
        'login accepted (2xx/302)': (r) =>
            r.status === 200 || r.status === 204 || r.status === 302,
        'login not rate-limited': (r) => r.status !== 429,
    });

    if (res.status === 429) {
        console.error(
            'Login hit Fortify throttle (5/min). Wait a minute or: docker compose exec php php artisan cache:clear',
        );
    }

    return ok;
}

/**
 * One Fortify login for the whole test run — call from setup(), pass cookies to VUs.
 */
export function loginForSetup() {
    const ok = login();

    if (!ok) {
        throw new Error(
            'setup login failed (often 429). Clear limiter: php artisan cache:clear — then retry.',
        );
    }

    const cookies = exportCookies();
    const names = Object.keys(cookies);

    if (!names.some((n) => n.includes('session'))) {
        throw new Error(
            `setup login produced no session cookie (got: ${names.join(', ') || 'none'})`,
        );
    }

    return cookies;
}

/** Per-VU: apply shared session cookies once. */
let sessionReady = false;

export function ensureSession(cookies) {
    if (sessionReady) {
        return;
    }

    importCookies(cookies);
    sessionReady = true;
}
