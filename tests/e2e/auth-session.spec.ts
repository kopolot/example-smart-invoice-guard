import { expect, test } from '@playwright/test';

test.describe('Authenticated session', () => {
    test('user can access dashboard with saved session', async ({ page }) => {
        await page.goto('/dashboard');

        await expect(page).toHaveURL(/\/dashboard$/);
        await expect(page.getByRole('heading', { name: 'Invoice performance at a glance' })).toBeVisible();
    });
});
