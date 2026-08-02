import { check } from 'k6';
import http from 'k6/http';
import { BASE_URL } from '../config.js';
import { pageHeaders } from './auth.js';

export function hitHome() {
    const res = http.get(`${BASE_URL}/`, {
        headers: { Accept: 'text/html' },
        tags: { endpoint: 'home' },
    });

    check(res, {
        'home status 200': (r) => r.status === 200,
    });

    return res;
}

export function hitDashboard() {
    const res = http.get(`${BASE_URL}/dashboard`, {
        headers: pageHeaders(),
        tags: { endpoint: 'dashboard' },
    });

    check(res, {
        'dashboard status 200': (r) => r.status === 200,
    });

    return res;
}

export function hitInvoices(page = 1) {
    const res = http.get(`${BASE_URL}/invoices?page=${page}`, {
        headers: pageHeaders(),
        tags: { endpoint: 'invoices' },
    });

    check(res, {
        'invoices status 200': (r) => r.status === 200,
    });

    return res;
}

export function searchInvoices(query) {
    const res = http.get(`${BASE_URL}/invoices?q=${encodeURIComponent(query)}`, {
        headers: pageHeaders(),
        tags: { endpoint: 'invoice_search' },
    });

    check(res, {
        'invoice search status 200': (r) => r.status === 200,
    });

    return res;
}

/** Browse flow: dashboard → invoice list → Elasticsearch/SQL search. */
export function browseAuthenticated() {
    hitDashboard();
    hitInvoices(1);
    searchInvoices('K6-SEARCH');
    hitInvoices(2);
}
