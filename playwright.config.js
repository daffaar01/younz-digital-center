import {defineConfig, devices} from '@playwright/test';

export default defineConfig({
    testDir: './tests/Browser',
    fullyParallel: true,
    forbidOnly: Boolean(process.env.CI),
    retries: process.env.CI ? 2 : 0,
    workers: process.env.CI ? 2 : undefined,
    reporter: [['list'], ['html', {open: 'never', outputFolder: 'storage/playwright-report'}]],
    use: {
        baseURL: process.env.E2E_BASE_URL ?? 'http://127.0.0.1:3000',
        screenshot: 'only-on-failure',
        trace: 'retain-on-failure',
    },
    projects: [
        {name: 'chromium-desktop', use: {...devices['Desktop Chrome']}},
        {name: 'chromium-mobile', use: {...devices['Pixel 7']}},
    ],
});
