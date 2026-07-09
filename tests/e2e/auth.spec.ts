import { expect, test } from '@playwright/test';

test.describe('Authentication', () => {
    test('guest is redirected to login from dashboard', async ({ page }) => {
        await page.goto('/dashboard');

        await expect(page).toHaveURL(/\/login$/);
        await expect(page.getByRole('button', { name: 'Log in' })).toBeVisible();
    });
});
