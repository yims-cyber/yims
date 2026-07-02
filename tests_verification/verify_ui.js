const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({
    args: [
      '--use-fake-ui-for-media-stream',
      '--use-fake-device-for-media-stream',
    ]
  });
  const page = await browser.newPage();

  // Set viewport for desktop
  await page.setViewportSize({ width: 1280, height: 800 });

  await page.goto('http://localhost:8000');

  console.log('Page title:', await page.title());

  // Click start button
  await page.click('#toggleBtn');
  console.log('Clicked Start button');

  // Wait for some analysis
  await page.waitForTimeout(5000);

  // Take screenshot
  await page.screenshot({ path: 'tests_verification/dashboard_desktop.png' });
  console.log('Screenshot saved: dashboard_desktop.png');

  // Verify metrics
  const dbValue = await page.innerText('#dbValue');
  const quality = await page.innerText('#qualityLabel');
  console.log(`Metrics - dB: ${dbValue}, Quality: ${quality}`);

  // Set viewport for mobile
  await page.setViewportSize({ width: 375, height: 667 });
  await page.waitForTimeout(2000);
  await page.screenshot({ path: 'tests_verification/dashboard_mobile.png' });
  console.log('Screenshot saved: dashboard_mobile.png');

  await browser.close();
})();
