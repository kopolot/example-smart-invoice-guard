import { execSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium  } from '@playwright/test';
import type {FullConfig, Page} from '@playwright/test';
import { e2eUser } from './helpers/auth';

const currentDir = dirname(fileURLToPath(import.meta.url));
const authFile = resolve(currentDir, '.auth/user.json');

async function prepareDatabase(): Promise<void> {
    execSync('docker compose exec -T php php artisan cache:clear --no-interaction', {
        cwd: resolve(currentDir, '../..'),
        stdio: 'inherit',
    });

    execSync('docker compose exec -T php php artisan db:seed --class=E2eSeeder --no-interaction', {
        cwd: resolve(currentDir, '../..'),
        stdio: 'inherit',
    });
}

async function assertLoginPageIsReady(page: Page): Promise<void> {
    await page.goto('/login');
    await page.waitForLoadState('domcontentloaded');

    try {
        await page.locator('input[name="email"]').waitFor({ timeout: 15000 });
    } catch {
        const html = await page.content();
        const usesViteDevServer = html.includes('https://localhost:5173') || html.includes('http://localhost:5173');

        if (usesViteDevServer) {
            throw new Error(
                [
                    'E2E could not load the login form because the app is pointing at the Vite dev server.',
                    'Start it with: docker compose exec -T php npm run dev',
                    'Or stop dev mode and use a production build: rm -f public/hot && docker compose exec -T php npm run build',
                ].join('\n'),
            );
        }

        throw new Error('E2E could not load the login form. Check https://localhost:8443/login in the browser.');
    }
}

export default async function globalSetup(config: FullConfig): Promise<void> {
    await prepareDatabase();

    mkdirSync(dirname(authFile), { recursive: true });

    const browser = await chromium.launch();
    const page = await browser.newPage({
        ignoreHTTPSErrors: true,
        baseURL: config.projects[0]?.use?.baseURL as string,
    });

    await assertLoginPageIsReady(page);
    await page.getByLabel('Email address').fill(e2eUser.email);
    await page.locator('input[name="password"]').fill(e2eUser.password);
    await page.getByRole('button', { name: 'Log in' }).click();
    await page.waitForURL(/\/dashboard$/, { timeout: 15000 });

    await page.context().storageState({ path: authFile });
    await browser.close();
}
