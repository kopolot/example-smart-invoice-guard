import { chromium, type FullConfig } from '@playwright/test';
import { execSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
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

export default async function globalSetup(config: FullConfig): Promise<void> {
    await prepareDatabase();

    mkdirSync(dirname(authFile), { recursive: true });

    const browser = await chromium.launch();
    const page = await browser.newPage({
        ignoreHTTPSErrors: true,
        baseURL: config.projects[0]?.use?.baseURL as string,
    });

    await page.goto('/login');
    await page.getByLabel('Email address').fill(e2eUser.email);
    await page.locator('input[name="password"]').fill(e2eUser.password);
    await page.getByRole('button', { name: 'Log in' }).click();
    await page.waitForURL(/\/dashboard$/, { timeout: 15000 });

    await page.context().storageState({ path: authFile });
    await browser.close();
}
