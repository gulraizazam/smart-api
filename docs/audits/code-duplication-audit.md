# Code Duplication Audit — ALLURA Upgradation

**Scope:** Full project.
**Deliverable type:** Read-only audit. No code changes in this pass.
**Source:** Three parallel Explore agents (date/TZ, helpers/services, cross-module) + targeted verification of high-signal claims.
**Framing doc:** `CLAUDE.md` — "Abstract on the third real repetition." "Business logic is never compromised by a change." "Thin controllers; logic in services/actions."

---

## 1. Executive summary

| Tier | Count | Total est. lines to consolidate |
|---|---|---|
| **A** — Exact / near-exact duplicates (safe after body diff) | 8 groups | ~500 lines |
| **B** — Semantic duplicates (same intent, different bodies) | 6 groups | ~1,200 lines |
| **C** — Scattered inline patterns (no function, repeated) | 7 groups | ~40 sites |
| **D** — Timezone / UTC conversion sprawl (CLAUDE.md gap) | 1 systemic | cross-cutting |
| **E** — False positives (documented so reviewers don't re-flag) | 2 | — |

**Risk breakdown**
- **HIGH** (money / patient / appointments / auth): 6 groups — A1, A2, A3, A4, B1, B4, D1
- **MEDIUM**: 9 groups — A5, A6, A7, B2, B3, B5, B6, C1–C5
- **LOW**: 3 groups — A8, C6, C7

**Headline findings**
1. **`parseDateRange` is defined FOUR TIMES** with subtly different bodies (indexed return vs. associative, `date('Y-m-d')` vs. `Carbon::createFromFormat`, exclude-today business logic baked into one). Merging naively would break behaviour.
2. **Timezone is broken at the edges.** Filter inputs are treated as UTC; DB stores UTC; `app.timezone` is `Asia/Karachi`. Financial report "today" may include tomorrow's transactions for users near midnight PKT.
3. **`Locations::getlocation()` is missing the `active` filter** — a **latent bug**, not just duplication. Any dropdown using it shows inactive branches.
4. **Five API controllers redefine `successResponse` / `failResponse` / `unauthorizedResponse` / `exceptionToResponse`** even though `app/Traits/ApiResponse.php` and `app/Traits/SimpleApiResponse.php` exist and are correctly used by three other controllers.
5. Six near-identical `status.blade.php` partials exist across modules; could collapse to one Blade component.

---

## 2. Tier A — Exact / near-exact duplicates

### A1. `parseDateRange` implemented 4× with divergent behaviour  · **HIGH**

| # | Location | Lines | Return shape | Time bounds | Format parser | Null handling |
|---|---|---|---|---|---|---|
| 1 | `app/Services/Reports/Concerns/ParsesDateRange.php` (trait) | 9–21 | `[null, null]` or indexed `[y-m-d, y-m-d]` | none | `strtotime` | returns `[null,null]` |
| 2 | `app/Services/Lead/LeadService.php::parseDateRange` | 1792–1804 | indexed | none | `strtotime` with `$parts[1] ?? $parts[0]` fallback | returns `[null,null]` |
| 3 | `app/Services/Feedback/FeedbackService.php::parseDateRange` | 294–305+ | indexed | `00:00:00` / `23:59:59` | `strtotime` | requires non-null; **exclude-today business rule baked in** |
| 4 | `app/Http/Controllers/Admin/InvoiceGenerationController.php::parseDateRange` | 610–618 | **associative `['from','to']`** | none | **`Carbon::createFromFormat('m/d/Y', ...)`** — strict m/d/Y | throws on malformed |

**Consumers not using the trait:**
- `LeadService.php:1510, 1572` (uses its own method, not trait)
- `FeedbackService.php:167` (uses its own method — has business logic)
- `InvoiceGenerationController.php:38, 79, 156` (uses its own method — different return shape AND format)

**Diff risk:** Cannot simply collapse into the trait. FeedbackService's "exclude today" rule is a business decision baked into parsing; InvoiceGenerationController's `m/d/Y` format validation is intentional input safety; LeadService's `$parts[1] ?? $parts[0]` handles single-date input.

**Suggested home:** One of:
- (a) Keep trait as pure parser; add `FeedbackService`'s exclude-today as a separate method in the service (not in parser).
- (b) Expand trait to return `Carbon` instances with optional `withTimeBounds(bool)` param; callers wrap for business rules.

**Characterization tests required before extraction:** malformed input (trailing spaces, m/d/Y vs d-m-Y input, empty string, single date, reversed order, dates spanning DST — though PKT has no DST, keep test for safety). Parity assertion per consumer: current behaviour == new trait behaviour + consumer-local rule.

**Rollback:** revert PR; no schema or config touched.

---

### A2. `AppointmentsController` inline `explode(' - ')` repeated 10× in one file  · **HIGH**

**File:** `app/Http/Controllers/Admin/Reports/AppointmentsController.php`
**Lines:** 117, 282, 422, 560, 700, 843, 1028, 1101, 1352, 1484 (10 occurrences of the same 3-line block)

```php
$date_range = explode(' - ', $request->get('date_range'));
$start_date = date('Y-m-d', strtotime($date_range[0]));
$end_date = date('Y-m-d', strtotime($date_range[1]));
```

Also appears at `app/Http/Controllers/Admin/Reports/Finance/FinanceArrivalReportController.php:141-143` (same block).

**Diff risk:** All 11 copies appear identical. Safe to replace with a trait method once A1 is resolved (use `ParsesDateRange` trait — it already returns the same shape).

**Suggested home:** Trait `ParsesDateRange` applied to both controllers.

**Characterization tests required:** Hit each report action with a known `date_range` fixture, snapshot the query. Swap implementation, re-run, diff must be zero.

**Rollback:** revert PR.

---

### A3. Three Report Request classes with duplicate `startDate()` / `endDate()`  · **HIGH**

| File | Lines | Adds time bounds? | Null-safe? |
|---|---|---|---|
| `app/Http/Requests/Reports/AppointmentsReportRequest.php` | 27–39 | **yes** (`00:00:00` / `23:59:59`) | no — throws on missing |
| `app/Http/Requests/Reports/GeneralSalesReportRequest.php` | 55–75 | no | yes — returns `null` |
| `app/Http/Requests/Reports/OperationsReportRequest.php` | 50–70 | no | yes — returns `null` |

**Diff risk:** The time-bounds difference is load-bearing in at least one consumer (the `whereBetween` on `created_at` vs. on a date-only column). Needs per-consumer check before merging.

**Suggested home:** Abstract base `AbstractDateRangeReportRequest` exposing `startDate(bool $withTimeBounds = false)` and `endDate(bool $withTimeBounds = false)`, or compose the `ParsesDateRange` trait.

**Characterization tests required:** per consumer, lock the exact SQL the query generates before and after.

**Rollback:** revert PR.

---

### A4. Five API controllers redefine response helpers that a trait already provides  · **HIGH**

**Existing traits (reuse targets):**
- `app/Traits/ApiResponse.php:12-49` — full helpers (`successResponse`, `errorResponse`, `handleException`)
- `app/Traits/SimpleApiResponse.php:16-41` — lightweight variant
- Already used correctly by: `DiscountsController`, `MembershipsController`, `MembershipTypesController`.

**Controllers carrying inline duplicates:**

| Controller | successResponse | failResponse | unauthorizedResponse | exceptionToResponse |
|---|---|---|---|---|
| `Api/BundlesController.php` | 348 | 359 | 370 | 381 |
| `Api/ServiceBundlesController.php` | 333 | 344 | 355 | 366 |
| `Api/ServicesController.php` | 424 | 435 | 446 | 457 |
| `Api/TreatmentController.php` | 317 | — | — | — |
| `Api/PatientController.php` | — | — | — | 532 |
| `Api/PlansController.php` | — | — | 285 | — |

**Diff risk:** Response envelope shape (`{success, data, meta}` vs. `{success:false, message, errors}`) is contractual for mobile clients. Every inline copy must be body-diffed against the trait before replacing — one typo in an early copy could have become the de-facto contract.

**Suggested home:** `ApiResponse` trait (or `SimpleApiResponse`, whichever matches observed envelope).

**Characterization tests required:** Feature test per endpoint asserting response JSON shape pre- and post-swap. Mobile team acceptance since this is an API contract.

**Rollback:** revert PR.

---

### A5. Three CashFlow services default to the same date range  · **MEDIUM**

Same block: default `date_from = startOfMonth`, `date_to = today`.

| File | Line | Carbon call |
|---|---|---|
| `app/Services/CashFlow/DashboardService.php` | 33 | `Carbon::now()->startOfMonth()` |
| `app/Services/CashFlow/ReportService.php` | 29, 98, 349 | `Carbon::now()->startOfMonth()` |
| `app/Services/CashFlow/VendorService.php` | 226 | `now()->startOfMonth()` (inconsistent: `now()` helper instead of `Carbon::now()`) |

**Diff risk:** Functionally identical (`now()` is the Carbon `now()` helper). Low risk to consolidate.

**Suggested home:** `CashflowHelper::defaultDateRange()` returning `[string, string]`.

**Characterization tests required:** Snapshot default filter output on a freeze-time test.

**Rollback:** revert PR.

---

### A6. ACL caching pattern copy-pasted 3× inside one file  · **MEDIUM**

**File:** `app/Helpers/ACL.php`

| Method | Lines | Cache variable |
|---|---|---|
| `getUserCentres` | 20–25 | `static $cachedLocations` |
| `getUserRegions` | 62–67 | `static $cachedRegions` |
| `getUserCities` | 89–94 | `static $cachedCities` |

Same structure in all three: `static $cachedX = []; $userId = Auth::id(); if (isset($cachedX[$userId])) return $cachedX[$userId];`.

**Suggested home:** private static helper `ACL::memoPerUser(string $key, callable $loader): mixed`.

**Characterization tests required:** Assert memoization still holds per user across multiple calls within a request.

**Rollback:** revert PR.

---

### A7. Blade `status.blade.php` — 6 near-identical partials  · **MEDIUM**

**Files (all exist, verified):**
- `resources/views/admin/custom_forms/status.blade.php`
- `resources/views/admin/doctors/status.blade.php`
- `resources/views/admin/locations/status.blade.php`
- `resources/views/admin/services/status.blade.php`
- `resources/views/admin/towns/status.blade.php`
- `resources/views/admin/users/status.blade.php`

**Variations to preserve:**
- Per-entity permission string (`custom_forms_inactive`, `doctor_inactive`, etc.)
- Per-entity variable name (`$custom_form`, `$user`, `$location`, `$town`)
- Two markup dialects: Laravel `Form::open()` (custom_forms, locations, services, towns) vs. bare `<form>` + `@csrf` + JS (`doctors`, `users`)

**Suggested home:** `resources/views/admin/components/status-toggle.blade.php` — accept props `:model`, `:permissionInactive`, `:permissionActive`, `:routeInactive`, `:routeActive`, `:idKey="id"`. Pick one markup dialect (recommend raw `<form>` + `@csrf` — aligns with CLAUDE.md CSRF guidance and lets us drop the deprecated `laravelcollective/html` package if present).

**Characterization tests required:** Visual regression on each list page; permission enforcement test per entity.

**Rollback:** revert PR.

---

### A8. HR modal partials with same scaffold  · **LOW**

Seven files under `resources/views/admin/hr/**/partials/*modal.blade.php`:
- `departments/partials/modal.blade.php`
- `designations/partials/modal.blade.php`
- `employees/partials/edit-modal.blade.php`
- `employees/partials/upload-modal.blade.php`
- `leave/applications/partials/review-modal.blade.php`
- `leave/types/partials/modal.blade.php`
- `recruitment/partials/create-modal.blade.php` (+ edit + interview)

**Diff risk:** Each modal has distinct body content but identical outer scaffold (header/X, scrollable body, footer Close button — matches the project's standard detail modal pattern).

**Suggested home:** `resources/views/admin/components/modal-form.blade.php` — slot-based wrapper.

**Characterization tests required:** None — visual regression only; no business logic.

**Rollback:** revert PR.

---

## 3. Tier B — Semantic duplicates (same intent, different bodies)

### B1. `Locations` model — 7 variants of `getActive*`, one with a latent bug  · **HIGH**

**File:** `app/Models/Locations.php`

| Method | Lines | Returns | Filters `active`? | Cached? |
|---|---|---|---|---|
| `getActiveSorted` | 173–193 | `pluck(name, id)` | yes | yes |
| `getActiveSortedLocations` | 195–213 | Collection | yes | no |
| `getActiveSortedStaffwisereport` | 218–236 | Collection (id only) | yes | no |
| `getLocationActiveSorted` | 241–259 | Collection | yes | no |
| **`getlocation`** | **264–274** | `pluck(name, id)` | **NO — latent bug** | no |
| `generalrevenuegetActiveSorted` | — | Collection | yes | no |
| `getActiveRecordsByCity` | — | Collection | yes | no |

**Verified bug:** `getlocation()` at `app/Models/Locations.php:264-274` queries by `account_id` only — no `active` filter. Any dropdown bound to it shows inactive/deleted branches. **This is a real bug surfaced by the audit, independent of dedup.**

**Suggested approach:**
1. Fix `getlocation()` first (separate PR) — add `->where('active', 1)` if that matches intent, OR rename to `getAllLocationsIncludingInactive()` if the absence is deliberate. Needs user decision.
2. Then consolidate the six remaining variants into 2–3 methods keyed by return shape (`->pluck` vs. `->get`).

**Characterization tests required:** For the bug fix — find every call site of `getlocation()` and verify intended behaviour. For consolidation — lock the output of each method against a fixture.

**Rollback:** revert PR; for the bug fix specifically, rollback = re-removing the `active` filter.

---

### B2. `User` model — 4 `getAllActive*` methods all marked `@deprecated`  · **HIGH**

**File:** `app/Models/User.php`

| Method | Lines | Join table |
|---|---|---|
| `getAllActiveRecords` | 393–417 | `user_has_locations` |
| `getAllActiveEmployeeRecords` | 418–443 | `user_has_locations` (structurally identical to above) |
| `getAllActivePractionersRecords` | 444–479 | `doctor_has_locations` |
| `getActiveOnly` | 480–515 | dispatches to `buildLocationBasedQuery` / `buildDirectQuery` |

All four marked `@deprecated`. An `ApplicationUserService` already exists — that's the intended home.

**Suggested home:** `ApplicationUserService::activeUsers(array $locationIds, ?string $joinTable = 'user_has_locations')`.

**Characterization tests required:** Per call site of each deprecated method, snapshot result set on fixture DB; parity after migration.

**Rollback:** revert PR.

---

### B3. Dashboard period logic — two abstractions  · **MEDIUM**

| File | Approach |
|---|---|
| `app/Helpers/DashboardHelper.php:20-27` | Enum-driven: `DashboardPeriod::fromRequest($period)->dateRange()` |
| `app/Helpers/DoctorDashboardHelper.php:165-185` | Discrete methods: `getThisMonthRange()`, `getLastMonthRange()` |

**Suggested home:** Extend `DashboardPeriod` enum to cover Doctor Dashboard cases; retire `DoctorDashboardHelper` methods.

**Characterization tests required:** Parity test for each period case on both helpers.

**Rollback:** revert PR.

---

### B4. `formatCurrency` — two completely different behaviours under the same name  · **MEDIUM**

| File | Lines | Output |
|---|---|---|
| `app/Helpers/CashflowHelper.php::formatCurrency` | 165–168 | `"PKR 1,234.56"` (full precision) |
| `app/Helpers/DoctorDashboardHelper.php::formatCurrency` | 111–120 | `"1.2M"` / `"1.2K"` / `"1,234"` (abbreviated) |

**Diff risk:** Merging would silently change money display. Do NOT merge — rename for clarity.

**Suggested approach:** rename to `formatCurrencyFull` and `formatCurrencyAbbreviated`. Not a merge — a disambiguation.

**Characterization tests required:** Output snapshot on fixture amounts (0, 999, 1000, 999_999, 1_000_000, negative).

**Rollback:** revert PR (it's just a rename + call-site updates).

---

### B5. `CashflowHelper` vs. `PoolService` / `CategoryService`  · **MEDIUM**

| Helper method | Line | Service equivalent | Line | Caching? |
|---|---|---|---|---|
| `CashflowHelper::getActivePools` | 67–79 | `PoolService::getActivePools` | 100–107 | **helper caches, service doesn't** |
| `CashflowHelper::getActiveCategories` | 84–94 | `CategoryService::getActive` | 32–38 | **helper caches, service doesn't** |
| `CashflowHelper::getActiveVendors` | 99–109 | — | — | helper only |
| `CashflowHelper::getActiveBranches` | 51–62 | — | — | helper only |

**Diff risk:** Unclear which is the canonical entry point. Pick services as canonical, move caching into services.

**Suggested home:** Services own the data + caching. Delete duplicated helper methods (keep helper only if still used by Blade directly — even then, delegate to service).

**Characterization tests required:** Cache hit-rate parity; assert `Cache::remember` keys unchanged (if any external code clears them by key).

**Rollback:** revert PR; keep the tenant-scoped cache keys documented.

---

### B6. Phone formatting chain has unnecessary indirection  · **LOW**

```
LeadHelper::formatPhone()
  → GeneralFunctions::prepareNumber4Call()
    → PhoneFormattingService::prepareNumber4Call()
```

**Suggested home:** `PhoneFormattingService` directly; drop the two-step hop.

**Characterization tests required:** Input/output parity on known phone formats.

**Rollback:** revert PR.

---

## 4. Tier C — Scattered inline patterns (no function, repeated)

### C1. `where('account_id', Auth::user()->account_id)` copy-pasted across 20+ helpers  · **MEDIUM**

Examples: `CashflowHelper.php:34-45, 56-61`, `LeadHelper.php:78-99`, `ServiceBundleHelper.php:23-40`, `BundleHelper.php:106-116`, `GeneralFunctions.php:77-162`.

**Suggested home:** Eloquent global scope `BelongsToAccount` or trait `AuthorizedQueries::forCurrentAccount($query)`.

**Characterization tests required:** Cross-account isolation tests (user from account A cannot see account B data) — these should exist anyway per HIPAA.

---

### C2. Inline `->format('Y-m-d')` on Carbon — 50+ sites  · **MEDIUM**

Hotspots: `app/Http/Controllers/Admin/Appointments/AppointmentScheduleController.php` (17 sites 121–773), `AppointmentExportController.php` (5 sites 315–591), `Services/Appointment/*` (8 sites).

**Suggested home:** `DateFormatter::toYmd(DateTimeInterface $d)` + `DateFormatter::toHuman(DateTimeInterface $d)`.

**Characterization tests required:** Output parity.

---

### C3. Dashboard KPI count queries inline in controllers  · **MEDIUM**

`HRDashboardController`, various `Reports/*`, `DashboardReportsController`. Repeated `User::where(...)->active()->count()` and appointment count variants.

**Suggested home:** per-domain service (`DashboardWidgetService`, `HRWidgetService`). Add `Cache::remember` per CLAUDE.md guidance.

---

### C4. Patient/Location/Service dropdown bootstrap repeated across controllers  · **MEDIUM**

At least 5 controllers independently load locations + services + doctors for their index filter bars (`Patients/PackagesController`, `PackageAdvancesController`, `LeadsController`, `AppointmentsController`, etc.).

**Suggested home:** `FormDataService::filterContextFor(User $user): array` returning the standard bundle.

---

### C5. Datatable filter persistence (`applyFilters` / `restoreSavedFilters`) inline in API controllers  · **MEDIUM**

Near-identical implementations in `Api/BundlesController.php:250-344`, `Api/ServiceBundlesController.php`, `Api/ServicesController.php`. ~50–100 lines each.

**Suggested home:** `FilterPersistenceService` injected into controllers; takes a `$filename` parameter (module key).

---

### C6. Gender / status label mappers scattered  · **LOW**

`LeadHelper::getGenderLabel`, `LeadHelper::parseGender`, and various inline switches across controllers.

**Suggested home:** PHP 8.4 backed enum with `label()` method. Already idiomatic per CLAUDE.md.

---

### C7. JS Select2 / daterangepicker initialization duplicated across blade views  · **LOW**

`admin/appointments/filters.blade.php`, `admin/appointments/treatment-filters.blade.php`, `admin/leads/filters.blade.php` each inline the same Select2 + AJAX + daterangepicker boilerplate.

**Suggested home:** `public/js/components/filter-bar.js` + data-attribute config on the markup.

---

## 5. Tier D — Timezone / UTC conversion sprawl (CLAUDE.md gap)

**CLAUDE.md requirement:** "All timestamps stored as UTC in the database; render in user's timezone at the edge."

**Current state (verified during today's activity-log fix):**
- `config/app.php` → `timezone = 'Asia/Karachi'`
- Only 5 files reference explicit timezone conversion (`DashboardHelper`, `ActivityLogService`, `DeliverNotSentAppointment`, `ThirdMessageBeforeAppointment`, `ActivityLogger`).
- Report controllers accept user-typed `startDate` / `endDate` and feed them straight into `whereBetween('created_at', [start, end])` with no UTC adjustment.

**Concrete failure mode (financial):**
- User in Karachi picks "2026-04-20" as the end date.
- Query becomes `whereBetween('created_at', ['2026-04-20 00:00:00', '2026-04-20 23:59:59'])`.
- If `created_at` is stored as UTC, a transaction made at **2026-04-20 22:00 PKT** is saved as `2026-04-20 17:00 UTC` — included, correct.
- But a transaction made at **2026-04-21 02:00 PKT** is saved as `2026-04-20 21:00 UTC` — **wrongly included in the 2026-04-20 report**.
- Inverse for the start date.

**Systemic fix suggestion (out of scope for this audit but flagged):** middleware that sets per-request timezone; `DateRangeBoundaries` service converting local day bounds → UTC bounds before the `whereBetween`. Same service used by every report.

**Characterization tests required before any systemic fix:** parity test at 23:30 PKT and 00:30 PKT — results before/after fix should differ *only* for boundary rows that were previously misclassified.

**Risk:** **HIGH** — touches every financial and clinical report. Must ship behind a flag or with a dated backfill plan.

---

## 6. Tier E — False positives (documented)

### E1. Policy methods (`viewAny`, `view`, `create`, `update`, `delete`) across `app/Policies/`

Intentional Laravel convention. Not duplication.

### E2. Form Request classes sharing structure (`rules()`, `messages()`, `authorize()`)

Framework convention. Only the rule *content* would be a duplicate; the class skeleton is not. Checked a sample — rules differ meaningfully per request.

---

## 7. Proposed PR backlog

Each row is a single-focus PR. HIGH-risk rows require characterization tests *merged first* before the refactor PR.

| # | Tier | Group | Risk | Characterization tests? | Depends on |
|---|---|---|---|---|---|
| 1 | A | A5 — CashFlow default date range | MED | Freeze-time snapshot | — |
| 2 | A | A6 — ACL memoization helper | MED | Per-user cache assertion | — |
| 3 | A | A8 — HR modal wrapper | LOW | Visual only | — |
| 4 | A | A7 — Status-toggle Blade component | MED | Visual + permission test | — |
| 5 | B | B6 — Phone formatting indirection | LOW | I/O parity | — |
| 6 | B | B4 — `formatCurrency` disambiguation (rename) | MED | Output snapshot | — |
| 7 | A | A2 — `AppointmentsController` date-parse trait | HIGH | **Yes** (per-action query snapshot) | Depends on #8 |
| 8 | A | A1 — `parseDateRange` consolidation | HIGH | **Yes** (per-consumer parity) | — |
| 9 | A | A3 — Report Request classes | HIGH | **Yes** (SQL snapshot per consumer) | Depends on #8 |
| 10 | B | B1a — `Locations::getlocation()` **bug fix** (add `active` filter) | HIGH | **Yes** (call-site audit + regression test) | — |
| 11 | B | B1b — `Locations` `getActive*` consolidation | HIGH | **Yes** (fixture output per method) | After #10 |
| 12 | B | B2 — `User::getAllActive*` → `ApplicationUserService` | HIGH | **Yes** (call-site parity) | — |
| 13 | A | A4 — API response trait adoption | HIGH | **Yes** (API contract tests + mobile sign-off) | — |
| 14 | B | B3 — DashboardPeriod enum extension | MED | Case-by-case parity | — |
| 15 | B | B5 — `CashflowHelper` → services | MED | Cache-key parity | — |
| 16 | C | C1 — `account_id` global scope / trait | MED | Cross-account isolation | — |
| 17 | C | C5 — `FilterPersistenceService` | MED | Filter round-trip | — |
| 18 | C | C4 — `FormDataService` | MED | Dropdown output parity | — |
| 19 | C | C3 — Dashboard widget service | MED | Count parity + cache key | — |
| 20 | C | C2 — `DateFormatter` helper | MED | Output parity | — |
| 21 | C | C6 — Gender/status enums | LOW | Label parity | — |
| 22 | C | C7 — JS filter-bar component | LOW | Visual | — |
| 23 | D | D1 — Timezone boundary fix | HIGH | **Yes** (boundary-hour tests) + feature flag | Scoped as separate initiative — do not bundle |

**Ordering guidance:** start with #1–#6 (LOW/MED, quick wins, build team muscle memory). Then #7–#15 (HIGH-risk clusters, paired with tests). Then #16–#22 (structural patterns). #23 (timezone) is a separate multi-PR initiative with its own rollout plan.

**Each PR must include:** diff summary, explicit test plan (verified / not verified), rollback SHA, and for HIGH-risk: a link to the characterization test PR that preceded it.

---

## 8. Out of scope for this audit

- No code changes this pass.
- No `CLAUDE.md` edits.
- No creation of new services/traits/helpers yet — only *proposed* locations.
- No attempt to certify any group "safe to merge" — that proof belongs in the individual extraction PR.
- No deep read of Jobs / Listeners / Observers (flagged for a follow-up audit pass).
- No performance audit (N+1, index coverage) — those are separate concerns, separate audits.

---

## 9. Next step handoff

1. User reads this document.
2. User marks each PR in the backlog as **IN** / **DEFERRED** / **OUT**.
3. Approved "IN" items become individual extraction PRs in follow-up passes. HIGH-risk items start with a characterization test PR that merges *first*; the refactor PR follows only after the tests are green on current code.
