import { test, expect, type Page } from '@playwright/test';

/**
 * Plans — bearer-mode smoke tests.
 *
 * This PR ships the read path only (list + detail + status/delete).
 * Create/edit is deliberately out of scope; the 8-table transactional
 * write flow will land in a dedicated follow-up.
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

test('Plans — global list loads with type + validity columns', async ({ page }) => {
  await seedAuth(page);
  const dataReady = page.waitForResponse((r) =>
    r.url().includes('/api/plans-optimized/global/datatable'),
  );
  await page.goto('/admin-v2/plans');
  await dataReady;

  await expect(page.getByRole('heading', { name: 'Plans' })).toBeVisible();

  const rowCount = await page.locator('main table tbody tr').count();
  expect(rowCount).toBeGreaterThan(0);

  // Filter pills render
  await expect(page.getByRole('button', { name: /^Type$/ })).toBeVisible();
  await expect(page.getByRole('button', { name: /^Status$/ })).toBeVisible();
});

test('Plans — clicking a row opens the detail modal with payment history', async ({ page }) => {
  await seedAuth(page);
  await page.goto('/admin-v2/plans');
  await page.waitForResponse((r) =>
    r.url().includes('/api/plans-optimized/global/datatable'),
  );

  const firstRow = page.locator('main table tbody tr').first();
  const detailReady = page.waitForResponse((r) => r.url().includes('/api/plans/display/'));
  await firstRow.click();
  const res = await detailReady;
  expect(res.ok()).toBe(true);

  // Header + summary cells
  await expect(page.getByRole('heading', { name: /^Plan #/ })).toBeVisible();
  await expect(page.getByText(/Cash received/)).toBeVisible();
  await expect(page.getByText(/Payment history/)).toBeVisible();

  // Close via footer button (the X in the DialogContent also has
  // aria-label="Close"; first() targets the footer button).
  await page.getByRole('dialog').getByRole('button', { name: /^Close$/ }).first().click();
  await expect(page.getByRole('heading', { name: /^Plan #/ })).toBeHidden();
});

test('Plans — patient-detail Plans tab renders stats + datatable', async ({ page }) => {
  await seedAuth(page);
  // Fetch a patient id whose stats endpoint returns > 0 plans via the
  // global datatable first — pick the first row's patient_id.
  await page.goto('/admin-v2/plans');
  const listRes = await page.waitForResponse((r) =>
    r.url().includes('/api/plans-optimized/global/datatable'),
  );
  const body = await listRes.json();
  const patientId = body?.data?.[0]?.patient_id;
  test.skip(!patientId, 'No seed plans to drive patient-tab test.');

  await page.goto(`/admin-v2/patients/${patientId}?tab=plans`);
  await page.waitForResponse((r) =>
    r.url().includes(`/api/plans-optimized/statistics/${patientId}`),
  );
  await page.waitForResponse((r) =>
    r.url().includes(`/api/plans-optimized/datatable/${patientId}`),
  );

  // Stats strip: "Plans", "Active", "Total", "Cash received", "Refunded"
  await expect(page.getByText('Plans', { exact: true }).first()).toBeVisible();
  await expect(page.getByText('Cash received', { exact: true }).first()).toBeVisible();

  // At least one data row in the patient-scoped table
  const rows = await page.locator('main table tbody tr').count();
  expect(rows).toBeGreaterThan(0);

  // Row click opens the detail modal
  const detailReady = page.waitForResponse((r) => r.url().includes('/api/plans/display/'));
  await page.locator('main table tbody tr').first().click();
  await detailReady;
  await expect(page.getByRole('heading', { name: /^Plan #/ })).toBeVisible();
});
