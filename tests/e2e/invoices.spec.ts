import { expect, test } from '@playwright/test';

test.describe('Invoice details and edit form', () => {
    test('show page formats dates and edit page pre-fills date inputs', async ({ page }) => {
        await page.goto('/invoices');
        await expect(page).toHaveURL(/\/invoices$/);

        await page.getByRole('link', { name: 'E2E-OVERDUE-001' }).click();

        await expect(page).toHaveURL(/\/invoices\/\d+$/);
        await expect(page.getByText('Jul 9, 2026')).not.toBeVisible();
        await expect(page.getByText('Jul 11, 2026')).not.toBeVisible();
        await expect(page.getByText('Jun 8, 2026')).toBeVisible();
        await expect(page.getByText('Jul 5, 2026')).toBeVisible();

        await page.getByRole('link', { name: 'Edit' }).click();

        await expect(page).toHaveURL(/\/edit$/);
        await expect(page.locator('input[name="date"]')).toHaveValue('2026-06-08');
        await expect(page.locator('input[name="due_date"]')).toHaveValue('2026-07-05');
    });
});
