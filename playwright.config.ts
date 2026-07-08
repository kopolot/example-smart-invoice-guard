import { defineConfig, devices } from '@playwright/test';

const authFile = 'tests/e2e/.auth/user.json';

export default defineConfig({
    testDir: './tests/e2e',
    globalSetup: './tests/e2e/global-setup.ts',
    fullyParallel: false,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,
    workers: 1,
    reporter: [['list']],
    use: {
        baseURL: process.env.E2E_BASE_URL ?? 'https://localhost:8443',
        ignoreHTTPSErrors: true,
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
    },
    projects: [
        {
            name: 'guest',
            testMatch: /auth\.spec\.ts/,
            use: { ...devices['Desktop Chrome'] },
        },
        {
            name: 'authenticated',
            testMatch: /(auth-session|dashboard)\.spec\.ts/,
            use: {
                ...devices['Desktop Chrome'],
                storageState: authFile,
            },
        },
    ],
});
