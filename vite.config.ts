import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig } from 'vite';
import basicSsl from '@vitejs/plugin-basic-ssl'

export default defineConfig({
    server: {
        host: '0.0.0.0',
        https: true,
        cors: {
            origin: [
                'https://smart-invoice-guard.com',
                'https://smart-invoice-guard.com:8080',
                'https://smart-invoice-guard.com:5173',
                'https://localhost:5173',
                'https://127.0.0.1:5173',
                'https://smart-invoice-guard.com:8443',
            ]
        },
        hmr: {
            host: 'smart-invoice-guard.com',
        },
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        inertia(),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        wayfinder({
            formVariants: true,
        }),
        basicSsl(),
    ],
});
