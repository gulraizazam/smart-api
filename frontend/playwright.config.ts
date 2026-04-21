import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright config for Cutera admin-v2 QA.
 *
 * Tests hit the Herd-served app at `http://cutera-upgradation.test`; auth
 * is handled by injecting a Sanctum PAT into `sessionStorage` before any
 * navigation, so the login page is bypassed. Mint a token ahead of each
 * run via `php artisan tinker --execute "echo App\\Models\\User::find(X)->createToken('qa',['*'],now()->addMinutes(30))->plainTextToken;"`
 * and export it as `CUTERA_QA_TOKEN=…` before `npx playwright test`.
 */
export default defineConfig({
  testDir: './tests/qa',
  timeout: 30_000,
  expect: { timeout: 8_000 },
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: 0,
  workers: 1,
  reporter: [['list'], ['html', { open: 'never', outputFolder: 'playwright-report' }]],
  use: {
    baseURL: 'http://cutera-upgradation.test',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    actionTimeout: 8_000,
    navigationTimeout: 15_000,
    ignoreHTTPSErrors: true,
  },
  projects: [
    {
      name: 'desktop',
      testIgnore: /services-cookie\.spec\.ts/,
      use: { ...devices['Desktop Chrome'], viewport: { width: 1280, height: 800 } },
    },
    {
      name: 'mobile',
      testIgnore: /services-cookie\.spec\.ts/,
      use: { ...devices['Pixel 7'] },
    },

    /* Cookie-mode project — runs `tests/qa/services-cookie.spec.ts`
       only, against a SPA built with `VITE_AUTH_MODE=cookie`. Each
       test does its own UI login; the suite is structured as two
       tests (foundation + long-form) so we stay well under the
       `/api/login` throttle:5,1 budget. */
    {
      name: 'cookie',
      testMatch: /services-cookie\.spec\.ts/,
      use: {
        ...devices['Desktop Chrome'],
        viewport: { width: 1280, height: 800 },
      },
    },
  ],
});
