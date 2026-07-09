import { expect, test } from '@playwright/test';

test.describe('Dashboard', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('/dashboard');
        await expect(page).toHaveURL(/\/dashboard$/);
    });

    test('shows business overview and metric cards', async ({ page }) => {
        await expect(page.getByText('Business overview')).toBeVisible();
        await expect(page.getByText('Total invoices')).toBeVisible();
        await expect(page.getByText('Collected revenue')).toBeVisible();
        await expect(page.getByText('Outstanding revenue')).toBeVisible();
        await expect(page.getByText('Average invoice')).toBeVisible();
    });

    test('shows overdue alert for overdue invoices', async ({ page }) => {
        await expect(page.getByText('Overdue invoices need attention')).toBeVisible();
        await expect(page.getByText(/1 invoice\(s\) passed their due date with \$75\.00/)).toBeVisible();
    });

    test('shows revenue chart and recent activity sections', async ({ page }) => {
        await expect(page.getByText('Revenue trend')).toBeVisible();
        await expect(page.getByText('Status breakdown')).toBeVisible();
        await expect(page.getByText('Recent activity')).toBeVisible();
        await expect(page.getByText('Quick reading')).toBeVisible();
        await expect(page.getByText('E2E-OVERDUE-001')).toBeVisible();
    });

    test('can navigate to invoices list from dashboard', async ({ page }) => {
        await page.locator('a[href="/invoices"]').first().click();

        await expect(page).toHaveURL(/\/invoices$/);
        await expect(page.getByRole('link', { name: 'Create Invoice' })).toBeVisible();
    });
});
