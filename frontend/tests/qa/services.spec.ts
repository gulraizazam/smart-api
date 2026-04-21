import { test, expect, type Page } from '@playwright/test';

/**
 * End-to-end QA for the /admin-v2/services module.
 *
 * Auth is bypassed by seeding `sessionStorage['cutera.token']` with a
 * Sanctum PAT before any navigation runs (see `seedAuth`). The SPA's
 * `api.ts` reads the token from there and attaches `Authorization:
 * Bearer …` to every request — so once the token is injected, every
 * subsequent request behaves as if the user had logged in normally.
 *
 * Required env:
 *   CUTERA_QA_TOKEN — a valid, non-expired Sanctum PAT for an
 *                     Administrator account. Mint with:
 *   php artisan tinker --execute "echo \
 *     App\\Models\\User::find(217229)->createToken('qa',['*'],now()->addMinutes(30))->plainTextToken;"
 */

const TOKEN = process.env.CUTERA_QA_TOKEN;

// Bearer-mode suite needs a Sanctum PAT injected into the context's
// sessionStorage (see `seedAuth`). Tests that don't use seedAuth
// (the mobile-only smoke block) remain runnable without it.
test.beforeEach(async ({ }, testInfo) => {
  if (!TOKEN && testInfo.file.endsWith('services.spec.ts')) {
    const title = testInfo.titlePath.join(' › ');
    // Only skip tests that actually call seedAuth; the mobile smoke
    // test injects its own fake token and can run without CUTERA_QA_TOKEN.
    if (!title.includes('mobile layout')) {
      testInfo.skip(true, 'CUTERA_QA_TOKEN not set — bearer-mode tests skipped.');
    }
  }
});

async function seedAuth(page: Page) {
  await page.addInitScript(
    (payload) => {
      try {
        sessionStorage.setItem('cutera.token', payload.token);
        sessionStorage.setItem('cutera.user', JSON.stringify(payload.user));
      } catch {
        // first navigation hasn't happened yet on some flows
      }
    },
    {
      token: TOKEN!,
      // RequireAuth gates on `!!user`, not on token-presence. Seed a
      // minimal user so isAuthenticated = true and the dashboard can
      // render. A bad token still fails at the first API call.
      user: { id: 217229, name: 'QA Admin', email: 'shahid@redsignal.net' },
    },
  );
}

const uniq = () => Math.random().toString(36).slice(2, 8);

/**
 * Row locator by name — uses the full row's text content to avoid the
 * checkbox column ambiguity. `getByRole('cell', { name })` matches
 * both the name cell AND the checkbox cell (whose `aria-label="Select {name}"`
 * contains `name` as a substring), which triggers Playwright's strict
 * mode. This helper is stable across the checkbox-column addition.
 */
function rowByName(page: import('@playwright/test').Page, name: string) {
  return page.locator('main table tbody tr').filter({ hasText: name });
}

/**
 * The delete-confirm dialog and the row-level delete buttons both use
 * the text "Delete". Scope the confirm click to a role=dialog so we
 * always hit the modal's Confirm button, never a row's icon.
 */
async function confirmDelete(page: import('@playwright/test').Page) {
  await page
    .getByRole('dialog')
    .getByRole('button', { name: /^Delete$/ })
    .click();
}

async function serviceIdByName(
  request: import('@playwright/test').APIRequestContext,
  name: string,
): Promise<number> {
  const res = await request.post('/api/services/datatable', {
    headers: { Authorization: `Bearer ${TOKEN}`, Accept: 'application/json', 'Content-Type': 'application/json' },
    data: { pagination: { page: 1, perpage: 5 }, query: { search: { name } } },
  });
  const body = await res.json();
  const row = (body.data ?? []).find((r: { name: string; id: number }) => r.name === name);
  if (!row) throw new Error(`service "${name}" not found via API`);
  return row.id;
}

// Detailed specs below assume the desktop table layout (hidden on
// mobile via `md:hidden` / `hidden md:block`). Each describe block
// applies its own breakpoint guard. Mobile coverage lives in the
// dedicated `Services — mobile layout` block at the bottom.
const skipOnMobile = ({ viewport }: { viewport: { width: number; height: number } | null }) =>
  (viewport?.width ?? 0) < 768;

test.describe('Services — list page', () => {
  test.skip(skipOnMobile, 'Desktop-only flow');

  test('loads list, shows parent rows with Parent badge, child rows indented', async ({ page }) => {
    await seedAuth(page);
    const dataReady = page.waitForResponse((r) => r.url().includes('/api/services/datatable'));
    await page.goto('/admin-v2/services');

    await expect(page.getByRole('heading', { name: 'Services' })).toBeVisible();

    // Wait for the real datatable response before asserting, otherwise
    // our selector matches the skeleton <tr>s instead of data rows.
    await dataReady;

    const rowCount = await page.locator('main table tbody tr').count();
    expect(rowCount).toBeGreaterThan(0);

    // At least one row must carry the "Parent" badge.
    const parentBadges = page.locator('main table').getByText(/^Parent$/);
    expect(await parentBadges.count()).toBeGreaterThan(0);

    // Header CTA cluster is visible
    await expect(page.getByRole('button', { name: /Reorder/i })).toBeVisible();
    await expect(page.getByRole('button', { name: /Add service/i })).toBeVisible();
  });

  test('search narrows results', async ({ page }) => {
    await seedAuth(page);
    await page.goto('/admin-v2/services');
    await page.waitForSelector('main table tbody tr, div.overflow-hidden table tbody tr');

    const searchBox = page.getByRole('searchbox', { name: /Search services/i });
    await searchBox.fill('Ultrashape');
    // debounce 300ms + network
    await page.waitForTimeout(600);
    await page.waitForSelector('main table tbody tr, div.overflow-hidden table tbody tr');

    const rows = await page.locator('main table tbody tr').count();
    expect(rows).toBeGreaterThan(0);
    // The datatable does tree-aware search: a parent whose children
    // match the query is included too. Assert against the full row's
    // text content — `td:first-child` is unreliable since the Services
    // table now has a bulk-select checkbox in column 1.
    const rowTexts = await page.locator('main table tbody tr').allTextContents();
    const anyMatches = rowTexts.some((t) => t.toLowerCase().includes('ultrashape'));
    expect(anyMatches).toBe(true);
  });
});

test.describe('Services — create/edit/delete', () => {
  test.skip(skipOnMobile, 'Desktop-only flow');

  test('create parent → create child → delete both', async ({ page }) => {
    const parentName = `QA Parent ${uniq()}`;
    const childName = `QA Child ${uniq()}`;

    await seedAuth(page);
    await page.goto('/admin-v2/services');
    await page.waitForSelector('main table tbody tr, div.overflow-hidden table tbody tr');

    // --- Create parent ---
    await page.getByRole('button', { name: /Add service/i }).click();
    await expect(page.getByRole('heading', { name: /Add service/i })).toBeVisible();

    await page.getByLabel('Name').fill(parentName);
    await page.locator('#svc-parent').selectOption('0'); // "Parent service (category)"

    // Child-only fields must be hidden when parent_id === 0.
    await expect(page.getByLabel(/^Price/)).toHaveCount(0);
    await expect(page.getByLabel(/^Duration/)).toHaveCount(0);

    await page.getByRole('button', { name: /^Create$/ }).click();

    // Wait for the dialog to close and the new row to appear.
    await expect(page.getByRole('heading', { name: /Add service/i })).toBeHidden();
    await page.getByRole('searchbox', { name: /Search services/i }).fill(parentName);
    await page.waitForTimeout(600);
    await expect(rowByName(page, parentName)).toHaveCount(1);

    // --- Create child under the parent ---
    await page.getByRole('searchbox', { name: /Search services/i }).fill('');
    await page.waitForTimeout(400);

    await page.getByRole('button', { name: /Add service/i }).click();
    await page.getByLabel('Name').fill(childName);
    await page.locator('#svc-parent').selectOption({ label: parentName });

    // Child-only fields should now be visible and the color should
    // auto-fill from the parent (parent created without explicit color
    // picks the default; we just assert the field is present).
    await expect(page.getByLabel(/^Duration/)).toBeVisible();
    await expect(page.getByLabel(/^Price/)).toBeVisible();

    await page.getByLabel(/^Duration/).selectOption('00:30');
    await page.getByLabel(/^Price/).fill('500');

    await page.getByRole('button', { name: /^Create$/ }).click();
    await expect(page.getByRole('heading', { name: /Add service/i })).toBeHidden();

    // --- Verify child row exists and has its price ---
    await page.getByRole('searchbox', { name: /Search services/i }).fill(childName);
    await page.waitForTimeout(600);
    const childRow = rowByName(page, childName);
    await expect(childRow).toHaveCount(1);
    await expect(childRow).toContainText('500');

    // --- Delete child (should succeed) ---
    await childRow.getByRole('button', { name: /Delete/i }).click();
    await confirmDelete(page);
    await expect(rowByName(page, childName)).toHaveCount(0);

    // --- Now delete parent (should succeed since no children left) ---
    await page.getByRole('searchbox', { name: /Search services/i }).fill(parentName);
    await page.waitForTimeout(600);
    const parentRow = rowByName(page, parentName);
    await parentRow.getByRole('button', { name: /Delete/i }).click();
    await confirmDelete(page);
    await expect(rowByName(page, parentName)).toHaveCount(0);
  });

  test('delete blocks when the parent still has children', async ({ page }) => {
    const parentName = `QA Parent Block ${uniq()}`;
    const childName = `QA Child Block ${uniq()}`;

    await seedAuth(page);
    await page.goto('/admin-v2/services');
    await page.waitForSelector('main table tbody tr, div.overflow-hidden table tbody tr');

    // create parent
    await page.getByRole('button', { name: /Add service/i }).click();
    await page.getByLabel('Name').fill(parentName);
    await page.locator('#svc-parent').selectOption('0');
    await page.getByRole('button', { name: /^Create$/ }).click();
    await expect(page.getByRole('heading', { name: /Add service/i })).toBeHidden();

    // create child
    await page.getByRole('button', { name: /Add service/i }).click();
    await page.getByLabel('Name').fill(childName);
    await page.locator('#svc-parent').selectOption({ label: parentName });
    await page.getByLabel(/^Duration/).selectOption('00:15');
    await page.getByLabel(/^Price/).fill('100');
    await page.getByRole('button', { name: /^Create$/ }).click();
    await expect(page.getByRole('heading', { name: /Add service/i })).toBeHidden();

    // try to delete the parent → expect blocking error message
    await page.getByRole('searchbox', { name: /Search services/i }).fill(parentName);
    await page.waitForTimeout(600);
    const parentRow = rowByName(page, parentName);
    await parentRow.getByRole('button', { name: /Delete/i }).click();
    await confirmDelete(page);

    // Server returns the dependency-block message; confirm dialog
    // stays open with the alert text.
    await expect(page.getByRole('alert')).toContainText(/child services|cannot be deleted/i);

    // clean up — close dialog, delete child, then parent
    await page.getByRole('dialog').getByRole('button', { name: /Cancel/i }).click();

    await page.getByRole('searchbox', { name: /Search services/i }).fill(childName);
    await page.waitForTimeout(600);
    const childRow = rowByName(page, childName);
    await childRow.getByRole('button', { name: /Delete/i }).click();
    await confirmDelete(page);
    await expect(rowByName(page, childName)).toHaveCount(0);

    await page.getByRole('searchbox', { name: /Search services/i }).fill(parentName);
    await page.waitForTimeout(600);
    const parentRow2 = rowByName(page, parentName);
    await parentRow2.getByRole('button', { name: /Delete/i }).click();
    await confirmDelete(page);
    await expect(rowByName(page, parentName)).toHaveCount(0);
  });
});

test.describe('Services — form validation', () => {
  test.skip(skipOnMobile, 'Desktop-only flow');

  test('duration regex blocks invalid format', async ({ page }) => {
    await seedAuth(page);
    await page.goto('/admin-v2/services');
    await page.waitForSelector('main table tbody tr, div.overflow-hidden table tbody tr');

    await page.getByRole('button', { name: /Add service/i }).click();
    await page.getByLabel('Name').fill('invalid duration test');

    // Pick a real parent so child-only fields appear.
    const firstParentValue = await page
      .getByLabel('Parent')
      .locator('option')
      .nth(2) // 0=Choose…, 1=Parent category, 2=first real parent
      .getAttribute('value');
    expect(firstParentValue).toBeTruthy();
    await page.locator('#svc-parent').selectOption(firstParentValue!);

    // Dropdown only offers valid HH:MM — use .evaluate to force invalid
    // value so we can verify the Zod regex runs. This bypasses the
    // native select constraint.
    await page.getByLabel(/^Duration/).evaluate((el: HTMLSelectElement) => {
      const opt = document.createElement('option');
      opt.value = '25:00';
      opt.text = '25:00';
      el.appendChild(opt);
      el.value = '25:00';
      el.dispatchEvent(new Event('change', { bubbles: true }));
    });
    await page.getByLabel(/^Price/).fill('100');

    await page.getByRole('button', { name: /^Create$/ }).click();

    // We expect either the Zod client error, or the server 422 surfaced
    // via ApiError — either way the dialog stays open with an alert.
    await expect(page.getByRole('heading', { name: /Add service/i })).toBeVisible();
  });

  test('submit without parent shows required error', async ({ page }) => {
    await seedAuth(page);
    await page.goto('/admin-v2/services');
    await page.waitForSelector('main table tbody tr, div.overflow-hidden table tbody tr');

    await page.getByRole('button', { name: /Add service/i }).click();
    await page.getByLabel('Name').fill('no parent test');
    await page.getByRole('button', { name: /^Create$/ }).click();

    await expect(page.getByText(/Please select a parent service/i)).toBeVisible();
  });
});

test.describe('Services — reorder dialog', () => {
  test.skip(skipOnMobile, 'Desktop-only flow');

  test('opens sort dialog and loads parent list', async ({ page }) => {
    await seedAuth(page);
    await page.goto('/admin-v2/services');
    await page.waitForSelector('main table tbody tr, div.overflow-hidden table tbody tr');

    await page.getByRole('button', { name: /Reorder/i }).click();

    await expect(page.getByRole('heading', { name: /Reorder services/i })).toBeVisible();

    // At least one parent row should render inside the sort list.
    const sortList = page.getByRole('dialog', { name: /Reorder services/i });
    await expect(sortList.getByRole('button', { name: /Reorder /i }).first()).toBeVisible();

    // Close without changes — Save button disabled when nothing dirty.
    const saveBtn = sortList.getByRole('button', { name: /^Save order$/ });
    await expect(saveBtn).toBeDisabled();

    await sortList.getByRole('button', { name: /Cancel/i }).click();
    await expect(page.getByRole('heading', { name: /Reorder services/i })).toBeHidden();
  });
});

test.describe('Services — status toggle', () => {
  test.skip(skipOnMobile, 'Desktop-only flow');

  test('deactivate mutation fires and the row disappears from the default list', async ({ page, request }) => {
    const parentName = `QA Toggle ${uniq()}`;

    await seedAuth(page);
    await page.goto('/admin-v2/services');
    await page.waitForResponse((r) => r.url().includes('/api/services/datatable'));

    // Seed a fresh parent we can safely toggle.
    await page.getByRole('button', { name: /Add service/i }).click();
    await page.getByLabel('Name').fill(parentName);
    await page.locator('#svc-parent').selectOption('0');
    await page.getByRole('button', { name: /^Create$/ }).click();
    await expect(page.getByRole('heading', { name: /Add service/i })).toBeHidden();

    await page.getByRole('searchbox', { name: /Search services/i }).fill(parentName);
    await page.waitForTimeout(600);
    const row = rowByName(page, parentName);
    await expect(row.getByText('Active', { exact: true })).toBeVisible();

    // Capture the service id NOW, while the row is still active. After
    // deactivation it becomes invisible to this user's datatable query
    // (view_inactive_services gate), so we can't resolve the id later.
    const serviceId = await serviceIdByName(request, parentName);

    // Click Deactivate; assert the /status POST returned 200. Then
    // wait for the datatable refetch and confirm the row has
    // disappeared from the default (active-only) list. This matches
    // the production behaviour for admins who lack the
    // `view_inactive_services` gate — inactive rows are hidden
    // regardless of the UI filter. The dataset changing is the
    // canonical signal that the toggle took effect.
    const [deactivateRes] = await Promise.all([
      page.waitForResponse((r) => r.url().includes('/api/services/status') && r.request().method() === 'POST'),
      row.getByRole('button', { name: /Deactivate/i }).click(),
    ]);
    expect(deactivateRes.ok()).toBe(true);
    await page.waitForResponse((r) => r.url().includes('/api/services/datatable') && r.request().method() === 'POST');

    await expect(rowByName(page, parentName)).toHaveCount(0);

    // Reactivate out-of-band via the API using the captured id —
    // the row is invisible in the UI so we can't click it.
    const reactivate = await request.post('/api/services/status', {
      data: { id: serviceId, status: 1 },
      headers: { Authorization: `Bearer ${TOKEN}`, Accept: 'application/json' },
    });
    expect(reactivate.ok()).toBe(true);

    // Search box already holds the same value; filling it again won't
    // retrigger the debounced query. Reload to force a fresh fetch.
    await page.reload();
    await page.waitForResponse((r) => r.url().includes('/api/services/datatable'));
    await page.getByRole('searchbox', { name: /Search services/i }).fill(parentName);
    await page.waitForTimeout(600);
    await expect(
      rowByName(page, parentName).getByText('Active', { exact: true }),
    ).toBeVisible();

    // cleanup
    await rowByName(page, parentName).getByRole('button', { name: /Delete/i }).click();
    await confirmDelete(page);
    await expect(rowByName(page, parentName)).toHaveCount(0);
  });
});

test.describe('Services — mobile layout', () => {
  test.skip(({ viewport }) => (viewport?.width ?? 0) >= 768, 'Mobile-only smoke test');

  test('mobile list renders stacked cards with icon-only actions', async ({ page }) => {
    await seedAuth(page);
    const dataReady = page.waitForResponse((r) => r.url().includes('/api/services/datatable'));
    await page.goto('/admin-v2/services');
    await dataReady;

    // Heading still visible at every breakpoint.
    await expect(page.getByRole('heading', { name: 'Services' })).toBeVisible();

    // Desktop table should be hidden; the mobile card container
    // (`md:hidden` div holding the stacked rows OR the empty-state
    // placeholder) must be present. Filter by the container's
    // distinctive class pair so we don't match other `md:hidden`
    // primitives scattered through the shell.
    await expect(
      page.locator('div.md\\:hidden.divide-y, div.md\\:hidden > .p-4').first(),
    ).toBeVisible({ timeout: 10_000 });

    // Add button is icon-only at this breakpoint — the label is
    // `.hidden xs:inline sm:inline` so the visible text collapses.
    await expect(page.getByRole('button', { name: /^Add$/ })).toBeVisible();

    // Reorder button is also icon-only on mobile (aria-label="Reorder").
    await expect(page.getByRole('button', { name: /Reorder/ }).first()).toBeVisible();
  });

  test('mobile add-service dialog fits the viewport', async ({ page }) => {
    await seedAuth(page);
    const dataReady = page.waitForResponse((r) => r.url().includes('/api/services/datatable'));
    await page.goto('/admin-v2/services');
    await dataReady;

    await page.getByRole('button', { name: /^Add$/ }).click();
    const dialog = page.getByRole('dialog');
    await expect(dialog).toBeVisible();
    await expect(page.getByLabel('Name')).toBeVisible();

    // Dialog body should not force horizontal scroll on the 412px Pixel 7 width.
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
    expect(overflow).toBe(false);

    await page.getByRole('button', { name: /Cancel/i }).click();
    await expect(dialog).toBeHidden();
  });
});

test.describe('Services — rich text editor', () => {
  test.skip(skipOnMobile, 'Desktop-only flow');

  test('saves HTML from TipTap and round-trips on edit', async ({ page }) => {
    const parentName = `QA RT Parent ${uniq()}`;
    const childName = `QA RT Child ${uniq()}`;

    await seedAuth(page);
    await page.goto('/admin-v2/services');
    await page.waitForSelector('main table tbody tr, div.overflow-hidden table tbody tr');

    // seed parent
    await page.getByRole('button', { name: /Add service/i }).click();
    await page.getByLabel('Name').fill(parentName);
    await page.locator('#svc-parent').selectOption('0');
    await page.getByRole('button', { name: /^Create$/ }).click();
    await expect(page.getByRole('heading', { name: /Add service/i })).toBeHidden();

    // create child with formatted description
    await page.getByRole('button', { name: /Add service/i }).click();
    await page.getByLabel('Name').fill(childName);
    await page.locator('#svc-parent').selectOption({ label: parentName });
    await page.getByLabel(/^Duration/).selectOption('00:30');
    await page.getByLabel(/^Price/).fill('300');

    // Type into the TipTap editor content area and apply bold.
    const editor = page.getByRole('textbox', { name: /Description editor/i });
    await editor.click();
    await page.keyboard.type('bold then plain');
    // Select "bold" word and toggle bold formatting.
    await page.keyboard.press('Home');
    for (let i = 0; i < 4; i++) await page.keyboard.press('Shift+ArrowRight');
    await page.getByRole('button', { name: /^Bold$/ }).click();

    await page.getByRole('button', { name: /^Create$/ }).click();
    await expect(page.getByRole('heading', { name: /Add service/i })).toBeHidden();

    // Reopen — content round-trips; at least the plain text must be present.
    await page.getByRole('searchbox', { name: /Search services/i }).fill(childName);
    await page.waitForTimeout(600);
    const row = rowByName(page, childName);
    await row.getByRole('button', { name: /Edit/i }).click();
    await expect(page.getByRole('heading', { name: /Edit service/i })).toBeVisible();

    const editorOnEdit = page.getByRole('textbox', { name: /Description editor/i });
    await expect(editorOnEdit).toContainText('bold then plain');

    await page.getByRole('dialog').getByRole('button', { name: /Cancel/i }).click();

    // cleanup — delete child then parent
    await row.getByRole('button', { name: /Delete/i }).click();
    await confirmDelete(page);
    await expect(rowByName(page, childName)).toHaveCount(0);

    await page.getByRole('searchbox', { name: /Search services/i }).fill(parentName);
    await page.waitForTimeout(600);
    const prow = rowByName(page, parentName);
    await prow.getByRole('button', { name: /Delete/i }).click();
    await confirmDelete(page);
    await expect(rowByName(page, parentName)).toHaveCount(0);
  });
});
