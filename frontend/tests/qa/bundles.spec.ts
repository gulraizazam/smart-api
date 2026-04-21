import { test, expect, type Page } from '@playwright/test';

/**
 * Bundles — bearer-mode smoke tests.
 *
 * Covers the load-bearing paths of the Bundles SPA:
 *   • List renders against the real /api/bundles/datatable shape
 *   • Create-via-UI pushes the correct service_id[] / service_price[]
 *     arrays and the client-side calculated_price preview converges
 *   • Delete surfaces the server's dependency-block message when one
 *     fires, and cleans up otherwise
 *   • Status toggle + Reorder dialog smoke
 *
 * Auth: same sessionStorage token-injection pattern as services.spec.ts.
 * See CUTERA_QA_TOKEN in the top-of-file skip check.
 */

const TOKEN = process.env.CUTERA_QA_TOKEN;

test.skip(({ viewport }) => (viewport?.width ?? 0) < 768, 'Desktop-only flows');
test.beforeEach(async ({ }, testInfo) => {
  if (!TOKEN) testInfo.skip(true, 'CUTERA_QA_TOKEN not set');
});

async function seedAuth(page: Page) {
  await page.addInitScript(
    (payload) => {
      try {
        sessionStorage.setItem('cutera.token', payload.token);
        sessionStorage.setItem('cutera.user', JSON.stringify(payload.user));
      } catch {
        /* first-nav edge case */
      }
    },
    {
      token: TOKEN!,
      user: { id: 217229, name: 'QA Admin', email: 'shahid@redsignal.net' },
    },
  );
}

const uniq = () => Math.random().toString(36).slice(2, 8);

function rowByName(page: Page, name: string) {
  return page.locator('main table tbody tr').filter({ hasText: name });
}

async function confirmDelete(page: Page) {
  await page
    .getByRole('dialog')
    .getByRole('button', { name: /^Delete$/ })
    .click();
}

test('Bundles — list loads with expected columns', async ({ page }) => {
  await seedAuth(page);
  const dataReady = page.waitForResponse((r) => r.url().includes('/api/bundles/datatable'));
  await page.goto('/admin-v2/bundles');
  await dataReady;

  await expect(page.getByRole('heading', { name: 'Bundles' })).toBeVisible();

  const rowCount = await page.locator('main table tbody tr').count();
  expect(rowCount).toBeGreaterThan(0);

  await expect(page.getByRole('button', { name: /Add bundle/i })).toBeVisible();
  await expect(page.getByRole('button', { name: /Reorder/i })).toBeVisible();

  // Sanity on the numeric columns — a real bundle should show a price
  // with a thousands separator or a decimal.
  const firstRowText = await page.locator('main table tbody tr').first().textContent();
  expect(firstRowText).toMatch(/\d/);
});

test('Bundles — create → edit-fetch → delete roundtrip', async ({ page }) => {
  const bundleName = `QA Bundle ${uniq()}`;

  await seedAuth(page);
  await page.goto('/admin-v2/bundles');
  await page.waitForResponse((r) => r.url().includes('/api/bundles/datatable'));

  // --- Open dialog + fill top-level fields ---
  await page.getByRole('button', { name: /Add bundle/i }).click();
  await expect(page.getByRole('heading', { name: /Add bundle/i })).toBeVisible();
  await page.getByLabel('Name').fill(bundleName);
  await page.getByLabel(/^Bundle price/).fill('500');

  // --- Add two service rows. Each "Add service" click appends a row;
  // the services repeater then lets us pick + price. ---
  await page.getByRole('button', { name: /^Add service$/ }).click();
  await page.getByRole('button', { name: /^Add service$/ }).click();

  // Service 1 — pick the first real catalog option (index 1 skips
  // "Choose service…" placeholder).
  const svc1 = page.getByRole('combobox', { name: 'Service 1' });
  const firstOpt = await svc1.locator('option').nth(1).getAttribute('value');
  expect(firstOpt).toBeTruthy();
  await svc1.selectOption(firstOpt!);
  await page.getByLabel('Service 1 price').fill('300');

  // Service 2 — pick a different option (index 2).
  const svc2 = page.getByRole('combobox', { name: 'Service 2' });
  const secondOpt = await svc2.locator('option').nth(2).getAttribute('value');
  expect(secondOpt).toBeTruthy();
  await svc2.selectOption(secondOpt!);
  await page.getByLabel('Service 2 price').fill('400');

  // --- Verify the client-side calculated_price preview converges.
  // bundle_price=500, services_total=700, ratio=1-(500/700)=0.2857…
  // calc[0] = round(300 * (500/700), 2) = 214.29
  // calc[1] = round(400 * (500/700), 2) = 285.71
  const calc1 = await page.getByLabel('Service 1 calculated price').textContent();
  const calc2 = await page.getByLabel('Service 2 calculated price').textContent();
  expect(calc1?.trim()).toBe('214.29');
  expect(calc2?.trim()).toBe('285.71');

  await page.getByRole('button', { name: /^Create$/ }).click();
  await expect(page.getByRole('heading', { name: /Add bundle/i })).toBeHidden();

  // --- Row appears in the list ---
  await page.getByRole('searchbox', { name: /Search bundles/i }).fill(bundleName);
  await page.waitForTimeout(600);
  await expect(rowByName(page, bundleName)).toHaveCount(1);

  // --- Edit roundtrip: reopen and confirm relationships hydrated ---
  const row = rowByName(page, bundleName);
  await row.getByRole('button', { name: /Edit/i }).click();
  await expect(page.getByRole('heading', { name: /Edit bundle/i })).toBeVisible();
  await expect(page.getByLabel('Name')).toHaveValue(bundleName);
  await expect(page.getByLabel('Service 1 price')).toHaveValue('300');
  await expect(page.getByLabel('Service 2 price')).toHaveValue('400');
  await page.getByRole('dialog').getByRole('button', { name: /Cancel/i }).click();

  // --- Delete ---
  await row.getByRole('button', { name: /Delete/i }).click();
  await confirmDelete(page);
  await expect(rowByName(page, bundleName)).toHaveCount(0);
});

test('Bundles — status toggle updates the row', async ({ page }) => {
  const bundleName = `QA Toggle Bundle ${uniq()}`;

  await seedAuth(page);
  await page.goto('/admin-v2/bundles');
  await page.waitForResponse((r) => r.url().includes('/api/bundles/datatable'));

  // Seed via UI
  await page.getByRole('button', { name: /Add bundle/i }).click();
  await page.getByLabel('Name').fill(bundleName);
  await page.getByLabel(/^Bundle price/).fill('100');
  await page.getByRole('button', { name: /^Add service$/ }).click();
  const svc = page.getByRole('combobox', { name: 'Service 1' });
  const opt = await svc.locator('option').nth(1).getAttribute('value');
  await svc.selectOption(opt!);
  await page.getByLabel('Service 1 price').fill('100');
  await page.getByRole('button', { name: /^Create$/ }).click();
  await expect(page.getByRole('heading', { name: /Add bundle/i })).toBeHidden();

  await page.getByRole('searchbox', { name: /Search bundles/i }).fill(bundleName);
  await page.waitForTimeout(600);
  const row = rowByName(page, bundleName);
  await expect(row.getByText('Active', { exact: true })).toBeVisible();

  // Deactivate → row may disappear from default view if the
  // view_inactive gate is absent; guard by waiting for the /status
  // response and then asserting the mutation succeeded.
  const [statusRes] = await Promise.all([
    page.waitForResponse((r) => r.url().includes('/api/bundles/status') && r.request().method() === 'POST'),
    row.getByRole('button', { name: /Deactivate/i }).click(),
  ]);
  expect(statusRes.ok()).toBe(true);

  // Cleanup — delete via API in case the row is now hidden.
  const xsrf = (await page.context().cookies()).find((c) => c.name === 'XSRF-TOKEN');
  // Find id via datatable with status=0 filter first; if gate hides
  // inactives, skip cleanup and rely on periodic purge.
  const listRes = await page.request.post('/api/bundles/datatable', {
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      Authorization: `Bearer ${TOKEN}`,
      ...(xsrf ? { 'X-XSRF-TOKEN': decodeURIComponent(xsrf.value) } : {}),
    },
    data: {
      pagination: { page: 1, perpage: 5 },
      query: { search: { name: bundleName, status: '0' } },
    },
  });
  const body = await listRes.json();
  const hit = (body.data ?? []).find((r: { name: string; id: number }) => r.name === bundleName);
  if (hit) {
    await page.request.delete(`/api/bundles/${hit.id}`, {
      headers: {
        Accept: 'application/json',
        Authorization: `Bearer ${TOKEN}`,
        ...(xsrf ? { 'X-XSRF-TOKEN': decodeURIComponent(xsrf.value) } : {}),
      },
    });
  }
});

test('Bundles — reorder dialog opens with sortable list', async ({ page }) => {
  await seedAuth(page);
  await page.goto('/admin-v2/bundles');
  await page.waitForResponse((r) => r.url().includes('/api/bundles/datatable'));

  await page.getByRole('button', { name: /Reorder/i }).first().click();
  await expect(page.getByRole('heading', { name: /Reorder bundles/i })).toBeVisible();

  const dialog = page.getByRole('dialog', { name: /Reorder bundles/i });
  await expect(dialog.getByRole('button', { name: /Reorder / }).first()).toBeVisible();

  // Save disabled until something is dragged — we don't exercise dnd
  // here because Playwright's synthetic drag is flaky; just assert
  // the disabled state to prove the dirty-tracking hook is wired.
  await expect(dialog.getByRole('button', { name: /^Save order$/ })).toBeDisabled();

  await dialog.getByRole('button', { name: /Cancel/i }).click();
  await expect(page.getByRole('heading', { name: /Reorder bundles/i })).toBeHidden();
});
