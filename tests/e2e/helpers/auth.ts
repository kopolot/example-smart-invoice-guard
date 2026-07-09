import { expect, type Page } from '@playwright/test';

export const e2eUser = {
    email: 'e2e@example.com',
    password: 'password',
};

export async function login(page: Page): Promise<void> {
    await page.goto('/login');
    await page.getByLabel('Email address').fill(e2eUser.email);
    await page.locator('input[name="password"]').fill(e2eUser.password);
    await page.getByRole('button', { name: 'Log in' }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
}
