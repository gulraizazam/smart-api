import { test, expect, type Page } from '@playwright/test';

/**
 * Cookie-mode end-to-end QA.
 *
 * The SPA has a single-point rate limit at `/api/login` (throttle:5,1),
 * so every extra "UI login per test" pattern blows through the budget.
 * Structure instead:
 *
 *   Test 1 — bare-metal: log in fresh, confirm landing + session cookie
 *            + no sessionStorage token.
 *   Test 2 — long form: one UI login, then in sequence exercise every
 *            cookie-auth code path (XSRF roundtrip on mutation, boot
 *            hydration via page reload, no-Authorization-header on the
 *            wire, /api/logout invalidation). Single browser context,
 *            single session.
 *
 * Assumes the SPA was built with `VITE_AUTH_MODE=cookie` (bundle at
 * `/public/admin-v2/`).
 *
 * Required env:
 *   CUTERA_QA_EMAIL    — admin email (default: shahid@redsignal.net)
 *   CUTERA_QA_PASSWORD — admin password
 *
 * Run with: `npx playwright test --project=cookie`
 */

const EMAIL = process.env.CUTERA_QA_EMAIL ?? 'shahid@redsignal.net';
const PASSWORD = process.env.CUTERA_QA_PASSWORD;

// This spec file is shared with the bearer-mode runner via
// `testMatch` exclusion, but Playwright still evaluates every file
// at module load. Skip rather than throw when the cookie-mode env
// isn't wired in the current run — that way `--project=desktop`
// (bearer) can coexist cleanly.
test.skip(!PASSWORD, 'CUTERA_QA_PASSWORD not set — cookie-mode tests skipped.');

async function uiLogin(page: Page) {
  await page.goto('/admin-v2/login');
  await expect(page.getByRole('heading', { name: /Welcome back/i })).toBeVisible();
  await page.getByLabel('Email').fill(EMAIL);
  await page.getByLabel('Password', { exact: true }).fill(PASSWORD!);
  const loginRes = page.waitForResponse((r) => r.url().endsWith('/api/login'));
  await page.getByRole('button', { name: /Sign in/i }).click();
  const res = await loginRes;
  expect(res.ok()).toBe(true);
  await page.waitForURL((url) => !/\/login/.test(url.pathname), { timeout: 15_000 });
}

const uniq = () => Math.random().toString(36).slice(2, 8);

test('foundation — fresh UI login lands authenticated, no bearer token in sessionStorage', async ({ page, context }) => {
  await uiLogin(page);
  await expect(page.getByRole('heading', { name: /Welcome back/i })).toHaveCount(0);

  const cookies = await context.cookies();
  expect(cookies.some((c) => c.name === 'laravel_session')).toBe(true);
  const hasXsrf = cookies.some((c) => c.name === 'XSRF-TOKEN');
  expect(hasXsrf).toBe(true);

  // Cookie mode must NOT pollute sessionStorage with a bearer token.
  const tokenInStorage = await page.evaluate(() => sessionStorage.getItem('cutera.token'));
  expect(tokenInStorage).toBeNull();
});

test('long form — XSRF + reload hydration + no-Authorization + logout invalidation', async ({ page, context }) => {
  test.slow(); // allow extra budget for the 4-phase flow

  await uiLogin(page);

  // ─── PHASE 1: services list loads with cookie-only auth ──────────
  // Exercises api.ts — Authorization header must be absent in cookie
  // mode. A residual token would signal sessionStorage leaking in.
  const datatableReq = page.waitForRequest((r) =>
    r.url().includes('/api/services/datatable'),
  );
  await page.goto('/admin-v2/services');
  const dtReq = await datatableReq;
  expect(dtReq.headers().authorization ?? null).toBeNull();
  await page.waitForResponse((r) => r.url().includes('/api/services/datatable'));
  await expect(page.getByRole('heading', { name: 'Services' })).toBeVisible();

  // ─── PHASE 2: mutation roundtrip (XSRF header flow) ──────────────
  // Creating a service triggers ensureCsrfCookie() on the first
  // mutation; subsequent POST attaches X-XSRF-TOKEN. Failure here
  // surfaces as a 419 from Laravel's CSRF middleware.
  const parentName = `QA Cookie ${uniq()}`;
  await page.getByRole('button', { name: /Add service/i }).click();
  await page.getByLabel('Name').fill(parentName);
  await page.locator('#svc-parent').selectOption('0');
  await page.getByRole('button', { name: /^Create$/ }).click();
  await expect(page.getByRole('heading', { name: /Add service/i })).toBeHidden();

  // Confirm the row actually landed.
  await page.getByRole('searchbox', { name: /Search services/i }).fill(parentName);
  await page.waitForTimeout(600);
  const row = page.locator('main table tbody tr').filter({ hasText: parentName });
  await expect(row).toHaveCount(1);

  // Clean up the created row before the next phase to keep the DB tidy.
  await row.getByRole('button', { name: /Delete/i }).click();
  await page.getByRole('button', { name: /^Delete$/ }).last().click();
  await expect(page.getByRole('cell', { name: parentName })).toBeHidden();

  // ─── PHASE 3: boot hydration ─────────────────────────────────────
  // Hard reload clears React state; AuthProvider must re-hydrate via
  // GET /api/user using only the session cookie and stay on the
  // protected route rather than bouncing to /login.
  const userReady = page.waitForResponse((r) => r.url().endsWith('/api/user'));
  await page.reload();
  const userRes = await userReady;
  expect(userRes.ok()).toBe(true);
  await page.waitForResponse((r) => r.url().includes('/api/services/datatable'));
  expect(new URL(page.url()).pathname).not.toMatch(/\/login/);

  // ─── PHASE 4: logout invalidates the session ─────────────────────
  // Server-side invalidation: we assert that after POST /api/logout,
  // the next protected navigation 401s and RequireAuth redirects.
  const xsrf = (await context.cookies()).find((c) => c.name === 'XSRF-TOKEN');
  const logoutRes = await context.request.post('/api/logout', {
    headers: {
      Accept: 'application/json',
      ...(xsrf ? { 'X-XSRF-TOKEN': decodeURIComponent(xsrf.value) } : {}),
    },
  });
  expect(logoutRes.ok()).toBe(true);

  await page.goto('/admin-v2/services');
  await page.waitForURL(/\/login/, { timeout: 10_000 });
  await expect(page.getByRole('heading', { name: /Welcome back/i })).toBeVisible();
});
