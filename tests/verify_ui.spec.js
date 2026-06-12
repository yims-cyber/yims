const { test, expect } = require('@playwright/test');
const path = require('path');

test.describe('Orateur Ultra UI Verification', () => {
  test.beforeEach(async ({ page }) => {
    const filePath = 'file://' + path.resolve(__dirname, '../index.html');
    await page.goto(filePath);
  });

  test('should have the correct title and theme elements', async ({ page }) => {
    await expect(page).toHaveTitle(/ORATEUR ULTRA/);
    const body = page.locator('body');
    const bgColor = await body.evaluate(el => window.getComputedStyle(el).backgroundColor);
    // Expect deep black background (#050505)
    expect(bgColor).toBe('rgb(5, 5, 5)');
  });

  test('should have 100vh layout on desktop', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    const body = page.locator('body');
    const overflow = await body.evaluate(el => window.getComputedStyle(el).overflow);
    expect(overflow).toBe('hidden');

    const height = await page.evaluate(() => window.innerHeight);
    expect(height).toBe(800);
  });

  test('should allow scroll on mobile', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 667 });
    const body = page.locator('body');
    const overflow = await body.evaluate(el => window.getComputedStyle(el).overflow);
    // On mobile media query it should be 'auto'
    expect(overflow).toBe('auto');
  });

  test('should contain essential UI components in French', async ({ page }) => {
    await expect(page.locator('#toggle-text')).toContainText('DÉMARRER SESSION');
    await expect(page.locator('#status-text')).toContainText('SYSTÈME EN ATTENTE');
    await expect(page.locator('h3:has-text("Logs de Session")')).toBeVisible();
  });
});
