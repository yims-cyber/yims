const { test, expect } = require('@playwright/test');

test.describe('Orateur Pro UI Verification', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto('http://localhost:8000/index.html');
    });

    test('should have the correct title and key elements', async ({ page }) => {
        await expect(page).toHaveTitle(/ORATEUR PRO/);
        await expect(page.locator('h1')).toContainText('Orateur Pro');
        await expect(page.locator('#toggleBtn')).toBeVisible();
        await expect(page.locator('#visualizer')).toBeVisible();
    });

    test('should display audio metrics', async ({ page }) => {
        await expect(page.locator('#noiseRate')).toBeVisible();
        await expect(page.locator('#snrValue')).toBeVisible();
        await expect(page.locator('#qualityMetric')).toBeVisible();
        await expect(page.locator('#secondaryVoices')).toBeVisible();
    });

    test('should be responsive - desktop', async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 800 });
        const sidebar = page.locator('.sidebar');
        const main = page.locator('.main-content');

        const sidebarBox = await sidebar.boundingBox();
        const mainBox = await main.boundingBox();

        expect(sidebarBox.width).toBeCloseTo(350, 1);
        expect(sidebarBox.x).toBe(0);
        expect(mainBox.x).toBeGreaterThanOrEqual(350);
    });

    test('should be responsive - mobile', async ({ page }) => {
        await page.setViewportSize({ width: 375, height: 667 });
        const sidebar = page.locator('.sidebar');
        const main = page.locator('.main-content');

        const sidebarBox = await sidebar.boundingBox();
        const mainBox = await main.boundingBox();

        expect(sidebarBox.width).toBeCloseTo(375, 1);
        expect(mainBox.y).toBeGreaterThanOrEqual(sidebarBox.height);
    });
});
