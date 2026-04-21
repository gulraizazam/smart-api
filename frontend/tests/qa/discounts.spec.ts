import { test, expect, type Page } from '@playwright/test';

/**
 * Discounts — bearer-mode smoke tests.
 *
 * Covers the load-bearing paths of the Discounts SPA:
 *   • List renders against the real /api/discounts/datatable shape
 *   • Simple (Percentage) create → list → edit prefill → delete
 *   • Configurable create wires up BUY + GET rows correctly and the
 *     backend returns the expected base_discount_services +
 *     get_discount_services on /edit
 *   • Allocation dialog opens and lists existing allocations
 *   • Status toggle
 *
 * Auth: sessionStorage injection, same pattern as services/bundles specs.
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
  await page.getByRole('dialog').getByRole('button', { name: /^Delete$/ }).click();
}

test('Discounts — list loads with type + validity columns', async ({ page }) => {
  await seedAuth(page);
  const dataReady = page.waitForResponse((r) => r.url().includes('/api/discounts/datatable'));
  await page.goto('/admin-v2/discounts');
  await dataReady;

  await expect(page.getByRole('heading', { name: 'Discounts' })).toBeVisible();
  await expect(page.getByRole('button', { name: /Add discount/i })).toBeVisible();

  // At least one row should render (tenant seeds have Percentage / Fixed discounts).
  const rowCount = await page.locator('main table tbody tr').count();
  expect(rowCount).toBeGreaterThan(0);
});

test('Discounts — simple Percentage create → edit prefill → delete', async ({ page }) => {
  const name = `QA Pct ${uniq()}`;

  await seedAuth(page);
  await page.goto('/admin-v2/discounts');
  await page.waitForResponse((r) => r.url().includes('/api/discounts/datatable'));

  await page.getByRole('button', { name: /Add discount/i }).click();
  await expect(page.getByRole('heading', { name: /Add discount/i })).toBeVisible();

  await page.getByLabel('Name').fill(name);
  // Type = Percentage by default
  await page.getByLabel('Amount (%)').fill('20');
  await page.getByLabel(/^Start/).fill('2026-01-01');
  await page.getByLabel(/^End/).fill('2026-12-31');
  // Check the Administrator role (id=1 in the fixture).
  const adminCheckbox = page.getByRole('checkbox', { name: 'Administrator' });
  if (!(await adminCheckbox.isChecked())) await adminCheckbox.check();

  await page.getByRole('button', { name: /^Create$/ }).click();
  await expect(page.getByRole('heading', { name: /Add discount/i })).toBeHidden();

  // Row appears
  const row = rowByName(page, name);
  await expect(row).toHaveCount(1);
  await expect(row).toContainText('Percentage');
  await expect(row).toContainText('20%');

  // Edit prefill — verify key fields round-trip
  await row.getByRole('button', { name: /Edit/i }).click();
  await expect(page.getByRole('heading', { name: /Edit discount/i })).toBeVisible();
  await expect(page.getByLabel('Name')).toHaveValue(name);
  await expect(page.getByLabel('Amount (%)')).toHaveValue('20');
  await expect(page.getByLabel(/^Start/)).toHaveValue('2026-01-01');
  await expect(page.getByLabel(/^End/)).toHaveValue('2026-12-31');
  await page.getByRole('dialog').getByRole('button', { name: /Cancel/i }).click();

  // Delete
  await row.getByRole('button', { name: /Delete/i }).click();
  await confirmDelete(page);
  await expect(rowByName(page, name)).toHaveCount(0);
});

test('Discounts — Configurable create wires Buy + Get rows end-to-end', async ({ page, request }) => {
  const name = `QA Cfg ${uniq()}`;

  await seedAuth(page);
  await page.goto('/admin-v2/discounts');
  await page.waitForResponse((r) => r.url().includes('/api/discounts/datatable'));

  await page.getByRole('button', { name: /Add discount/i }).click();
  await page.getByLabel('Name').fill(name);

  // Switch to Configurable
  await page.getByRole('radio', { name: 'Configurable' }).check();

  // BUY — use the first catalog service
  await page.getByLabel(/Sessions to buy/).selectOption('2');
  const bsSelect = page.locator('#cfg-bs');
  const firstOpt = await bsSelect.locator('option').nth(1).getAttribute('value');
  expect(firstOpt).toBeTruthy();
  await bsSelect.selectOption(firstOpt!);

  await page.getByLabel(/^Start/).fill('2026-01-01');
  await page.getByLabel(/^End/).fill('2026-12-31');
  const adminCheckbox = page.getByRole('checkbox', { name: 'Administrator' });
  if (!(await adminCheckbox.isChecked())) await adminCheckbox.check();

  // Add one GET row (Complimentary, 1 session)
  await page.getByRole('button', { name: /^Add row$/ }).click();
  // The new row should be at index 0 → aria-labels use "GET row 1 …"
  await page.getByLabel('GET row 1 sessions').selectOption('1');
  // services_name picker — pick the second option (skip "Choose…")
  const getSvc = page.getByRole('combobox', { name: 'GET row 1 service' });
  const svcOpt = await getSvc.locator('option').nth(1).getAttribute('value');
  await getSvc.selectOption(svcOpt!);
  // disc_type stays "complimentory" by default

  await page.getByRole('button', { name: /^Create$/ }).click();
  await expect(page.getByRole('heading', { name: /Add discount/i })).toBeHidden();

  // Row appears with Configurable badge
  const row = rowByName(page, name);
  await expect(row).toHaveCount(1);
  await expect(row).toContainText('Configurable');

  // Verify server-side shape via API: /edit returns base + get rows
  const hits = await request.post('/api/discounts/datatable', {
    headers: { Authorization: `Bearer ${TOKEN}`, 'Content-Type': 'application/json', Accept: 'application/json' },
    data: { pagination: { page: 1, perpage: 1 }, query: { search: { name } } },
  });
  const body = await hits.json();
  const id = body.data?.[0]?.id;
  expect(id).toBeTruthy();
  const edit = await request.get(`/api/discounts/${id}/edit`, {
    headers: { Authorization: `Bearer ${TOKEN}`, Accept: 'application/json' },
  });
  const editBody = await edit.json();
  expect(editBody.data.discount.type).toBe('Configurable');
  expect(editBody.data.base_discount_services.length).toBe(2); // sessions_buy=2 → 2 rows
  expect(editBody.data.get_discount_services.length).toBe(1);

  // Clean up via API
  await request.delete(`/api/discounts/${id}`, {
    headers: { Authorization: `Bearer ${TOKEN}`, Accept: 'application/json' },
  });
});

test('Discounts — allocation dialog opens for an existing discount', async ({ page }) => {
  await seedAuth(page);
  await page.goto('/admin-v2/discounts');
  await page.waitForResponse((r) => r.url().includes('/api/discounts/datatable'));

  const firstRow = page.locator('main table tbody tr').first();
  await firstRow.getByRole('button', { name: /^Allocate$/ }).click();

  await expect(page.getByRole('heading', { name: /Allocate discount/i })).toBeVisible();
  // The centre select should render with optgroups.
  await expect(page.getByLabel(/^Centre$/)).toBeVisible();

  // Two elements have accessible name "Close": the footer button
  // (rendered first) and the DialogContent X-icon (with sr-only
  // "Close" span). `.first()` targets the footer button.
  await page.getByRole('dialog').getByRole('button', { name: /^Close$/ }).first().click();
  await expect(page.getByRole('heading', { name: /Allocate discount/i })).toBeHidden();
});

test('Discounts — status toggle fires the mutation', async ({ page }) => {
  const name = `QA Toggle ${uniq()}`;

  await seedAuth(page);
  await page.goto('/admin-v2/discounts');
  await page.waitForResponse((r) => r.url().includes('/api/discounts/datatable'));

  // Seed via UI
  await page.getByRole('button', { name: /Add discount/i }).click();
  await page.getByLabel('Name').fill(name);
  await page.getByLabel('Amount (%)').fill('10');
  await page.getByLabel(/^Start/).fill('2026-01-01');
  await page.getByLabel(/^End/).fill('2026-12-31');
  const adminCheckbox = page.getByRole('checkbox', { name: 'Administrator' });
  if (!(await adminCheckbox.isChecked())) await adminCheckbox.check();
  await page.getByRole('button', { name: /^Create$/ }).click();
  await expect(page.getByRole('heading', { name: /Add discount/i })).toBeHidden();

  const row = rowByName(page, name);
  await expect(row.getByText('Active', { exact: true })).toBeVisible();

  const [res] = await Promise.all([
    page.waitForResponse((r) => r.url().includes('/api/discounts/status') && r.request().method() === 'POST'),
    row.getByRole('button', { name: /Deactivate/i }).click(),
  ]);
  expect(res.ok()).toBe(true);

  // Cleanup — fetch ID and DELETE via API (row may be hidden after toggle)
  const hits = await page.request.post('/api/discounts/datatable', {
    headers: { Authorization: `Bearer ${TOKEN}`, 'Content-Type': 'application/json', Accept: 'application/json' },
    data: { pagination: { page: 1, perpage: 1 }, query: { search: { name, status: '0' } } },
  });
  const body = await hits.json();
  const id = body.data?.[0]?.id;
  if (id) {
    await page.request.delete(`/api/discounts/${id}`, {
      headers: { Authorization: `Bearer ${TOKEN}`, Accept: 'application/json' },
    });
  }
});
