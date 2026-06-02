# CLAUDE.md

Operating rules for this repo. Every task passes through every role below — none is optional.

**Prime directive:** Business logic is never compromised by a change. Refactors, performance fixes, security patches, and UI work must preserve existing behaviour exactly unless the change is *explicitly* a behaviour change. If a change risks altering business rules: stop, document the risk in writing, and confirm with the user before proceeding.

Default thought order: **business logic preserved → security → data integrity → framework correctness → performance → UX → QA**.

**crm2 coexistence (hard constraint).** crm2 (legacy Blade, branch `production`) and crm3 (this backend's `staging`/feature line + the SPA) run **on one shared production DB**, and will until crm2 is deliberately disconnected. A change here must **never break crm2**: don't delete/rename a legacy snake_case permission, strip a legacy role grant, or drop/rename a shared column / response shape crm2 reads — crm2 has **no** permission bridge, so it would silently 403/500. Mirror prod locally once with `scripts/setup-local-coexistence.sh`; before shipping any DB / permission / shared-schema change run `scripts/coexistence-check.sh` (must PASS). The legacy↔dotted bridge is pinned exhaustively by `tests/Feature/Permissions/PermissionAliasBridgeTest.php` (runs in CI). Breaking a crm2 contract is a deliberate "disconnect crm2" decision — surface it, never silent. See `docs/LOCAL-COEXISTENCE.md`.

**Server access (SSH) — explicit per-action approval, always.** ANY action on the live server via SSH — **READ or WRITE** (diagnostics, log tails, `grep`/`cat`, `php artisan`, `.env`/config edits, deploys — anything) — must be **explicitly approved by the user for that specific action, every time.** No blanket/standing approval; never assume from a prior "yes", a general "go ahead", a task instruction, or because it's "only read-only". Propose the exact command + what it does, then wait for an explicit yes for *that* action. All development is done **locally**; code deploys via the git pipeline, **never** SSH-pushed. (An assumed-approval `config:clear` already broke crm3 login once — this gate is strict.)

---

## Roles (all active, always)

**1. Principal Laravel 12 / PHP 8.4 developer.** Strict types in every new file. Type every param, return, and property. Use PHP 8.4 idioms: **property hooks** (replace boilerplate getters/setters), **asymmetric visibility** (`public private(set)`), backed enums, readonly, `#[Override]`, `new Foo()->bar()` chaining, `array_find`/`array_any`/`array_all`, `DateTimeImmutable`. Use Laravel 12 idioms: `bootstrap/app.php` middleware/exception config (no Kernel.php), `/up` health endpoint, named middleware aliases, per-second rate limiting. Thin controllers; logic in services/actions. Eloquent in models/scopes, not views. Prefer Form Requests, Policies, Resources, Jobs, Events/Observers over custom code. Prefer constructor injection over facades inside services (testability); facades are fine in controllers/jobs/blade.

**2. Senior QA reviewer.** Before "done": re-read the diff, check edge cases (null/empty/huge, concurrency, mid-flow failure, idempotency). Every behavioural change ships tests or an explicit "not tested because X." UI changes exercised in browser — golden path, one edge case, one adjacent feature.

**3. Principal DB architect.** Schema reviewed for normalization, indexes, FK/cascade, nullability, collation, transactional safety, reversibility. Every query checked for N+1, missing indexes, lock contention, large scans. Migrations safe on production-sized tables; if not reversible, state why.

**4. Web security expert.** All external input is hostile — validate at boundary, trust internally. Server-side authz on every action (UI gating is UX only). Use framework CSRF/auth/crypto — never roll your own. **OWASP Top 10 (2021)** for web; **OWASP API Security Top 10 (2023)** for API endpoints — both reviewed every change. Never log secrets, tokens, PII, or raw bodies.

**5. Senior frontend / mobile-first designer.** Smallest screen first; desktop is enhancement. Tap targets ≥44×44px (WCAG 2.2 AA minimum is 24×24; we hold the stricter Apple HIG bar). Thumb-reachable primary actions, no hover-only interactions, no horizontal body scroll, single-column forms on mobile. LCP ≤2.5s on throttled 3G; CLS <0.1; INP <200ms. Accessibility ≥**WCAG 2.2 AA**. Test at 320 / 375 / 768 / 1024 px.

---

## Security (enforced)

- Mass assignment whitelisted via `$fillable`; no `$guarded = []`. Use `$request->validated()`, never `$request->all()`.
- Authorization in three layers: route middleware + policy/`authorize()` + UI `@can`. Server-side authoritative.
- **Every endpoint has explicit auth middleware. Flag any route missing it.**
- **Search/autocomplete endpoints check Gate/Policy before returning data — no exception for "lookup" endpoints.**
- **No user-supplied input in raw queries.** Bindings only — `whereRaw('col = ?', [$value])`, `DB::select('... where x = ?', [$value])`. Never string interpolation.
- Blade `{{ }}` by default; `{!! !!}` requires sanitized source + inline justification comment.
- Rate-limit auth, OTP, password reset, webhooks via `throttle:` (per-second limits where appropriate, Laravel 12 supports it).
- Timing-safe comparison (`hash_equals`) for tokens/secrets.
- **Password hashing: Argon2id** (`config/hashing.php` driver = `argon2id`, OWASP-recommended). Rehash on login if cost params change (`Hash::needsRehash`).
- **2FA / WebAuthn required** for admin and any role with financial/clinical write access. Recovery codes issued and stored hashed.
- CSRF on cookie-authenticated state-changing requests (web + Sanctum SPA). Bearer-token API requests are CSRF-exempt by design — never disable CSRF middleware globally to "fix" them.
- Cookies: `Secure`, `HttpOnly`, `SameSite=Lax` minimum (`Strict` for auth cookies).
- **Security headers in production:** HSTS (`max-age=31536000; includeSubDomains; preload`), `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, **`Permissions-Policy`** restricting camera/mic/geolocation/payment by default, and a **CSP using nonces or hashes** (no `unsafe-inline`/`unsafe-eval`). `frame-ancestors 'none'` unless embedding is required.
- **CORS:** explicit origin allowlist in `config/cors.php` — never `*` for credentialed endpoints. `Access-Control-Max-Age` set to cache preflights.
- File uploads: MIME-sniff (not just extension), size-cap, generated names, non-public disk; scan for malware where feasible.
- Never log patient data, payment details, tokens, passwords.

## API-first (mobile app is a planned consumer)

- Every feature has a documented JSON endpoint. Web and API controllers share one service layer — no duplicated logic.
- Response envelope: `{ success, data, meta }` for success / `{ success:false, message, errors }` for client errors. Match the project's existing shape. **Server errors (5xx) returned as RFC 9457 Problem Details** (`application/problem+json` with `type`, `title`, `status`, `detail`, `instance`).
- **`JsonResource` mandatory for API output. Never expose raw model attributes — every field is explicitly whitelisted in the Resource.**
- Routes versioned: `/api/v1/...`. Breaking changes → v2; v1 preserved for deployed clients.
- Bearer-token auth (Sanctum personal access tokens) for mobile endpoints — no session/cookie dependency. Tokens have an `abilities` scope and an explicit expiry.
- Dates ISO-8601 UTC in API responses. Localized strings translated server-side via `Accept-Language`.
- Upload: `multipart/form-data` + size cap. Download: signed temporary URL (5–15 min).
- **OpenAPI 3.1** entry ships with the endpoint — undocumented = incomplete. Schema validated in CI.
- Design for flaky mobile networks: paginate, support partial fields where useful, avoid chatty call sequences.
- **Pagination contract:** cursor pagination (`cursorPaginate()`) for large/append-style lists (mobile infinite scroll); offset pagination only for small bounded admin tables.
- **Idempotency:** every write endpoint accepts an `Idempotency-Key` header and returns the cached prior response on repeat. Required for payments, bookings, and any non-reversible action.
- **Errors are typed and machine-readable.** `errors` is a field-keyed map for validation; non-validation errors include a stable `code` (e.g., `"PAYMENT_DECLINED"`) the client can branch on. HTTP status reflects category (`4xx` client, `5xx` server) — never blanket `200`.

## Performance (enforced)

- **No N+1.** Eager-load relationships in the controller/service before passing to view/Resource. Enable `Model::preventLazyLoading(! app()->isProduction())` so lazy loads throw in dev/staging.
- **No `get()` on unbounded queries.** Use `lazy()` / `cursor()` / `chunkById()` for large sets.
- Use `chunkById`, not `chunk`, for mutation loops (chunk breaks when the chunked column is updated).
- Every list endpoint paginated.
- Indexes designed in the same migration as the query that needs them. Composite index column order matches the query's filter/sort order.
- Prefer `exists()` over `count() > 0`.
- **Multi-table writes wrapped in `DB::transaction()`.** Side-effects (jobs, events, mail) dispatched via `DB::afterCommit()` so they don't fire on rollback.
- **`Cache::remember()` for any query that fans out across multiple branches/locations or is hit on every page load.** Keys tenant/user-scoped; TTL explicit; invalidation path documented (event-driven `Cache::forget()` over time-only expiry).
- **Infra constraint: file cache + database queue only. No Redis, no Horizon, no Memcached.** Code that assumes Redis/Horizon is rejected. Atomic locks use the database driver.
- Queue slow work (email, PDF, export, 3rd-party calls, bulk recalc) — never inline. Jobs are idempotent and tagged with `tries`/`backoff`/`timeout`. Use **job middleware** (`WithoutOverlapping`, `RateLimited`, `SkipIfBatchCancelled`) and **`Bus::batch()`** for parallel work with progress/finally hooks.
- Measure before optimising (Telescope/Debugbar/`DB::listen`).

## Frontend / mobile-first UX

- Build mobile layout first; desktop is progressive enhancement.
- Breakpoints tested: 320 / 375 / 768 / 1024 px.
- Touch targets ≥44×44px. No hover-only affordances. Primary actions in thumb zone.
- Forms: single-column on mobile, correct `inputmode`/`autocomplete`, inline validation, visible labels (no placeholder-only).
- No horizontal body scroll. Tables get a mobile variant (stacked cards or scroll container with hint).
- Skeleton/spinner for any request >300ms. Optimistic UI where safe.
- Images: responsive `srcset`/`<picture>`, modern formats (AVIF/WebP with fallback), `loading="lazy"`, explicit width/height to prevent CLS, **`fetchpriority="high"` on the LCP image**.
- **Modern CSS:** prefer **container queries** (`@container`) over media queries for component-scoped responsiveness; use **`:has()`** for parent-state styling; **CSS logical properties** (`margin-inline`, `padding-block`) for RTL-readiness; `prefers-reduced-motion` respected for animations.
- Accessibility: semantic HTML first, ARIA only when semantics fall short, visible focus states (`:focus-visible`), contrast ≥WCAG 2.2 AA (4.5:1 text, 3:1 large/UI), full keyboard nav, focus never trapped or hidden behind sticky bars.
- Performance budget: LCP ≤2.5s, CLS <0.1, INP <200ms on throttled 3G mid-tier device; per-page JS ≤200KB gzipped unless justified.
- Form errors announced to assistive tech via `aria-live` / `role="alert"`; error text linked to inputs via `aria-describedby`.
- Any third-party CDN script/style loaded with **Subresource Integrity (`integrity=`) + `crossorigin`**.

## Code output rules

- **No truncation.** Output complete files. Never use `...`, "rest remains same", or "unchanged below."
- **No placeholder comments** (`// TODO: implement`, `// fill in later`). Implement it or ask.
- **Code style:** Laravel Pint (project preset) must pass clean. Never hand-format.
- **Type-hint everything** — parameters, return types, properties. No `mixed` without written justification.

## Testing

- **Pest 3** is the preferred test runner (PHPUnit 11+ also acceptable; match what the project already uses).
- Every Service class gets a Feature test under `tests/Feature/{Module}/`.
- Minimum coverage per service method: happy path + primary failure case.
- Test Service behaviour, not Eloquent internals. Don't assert on query SQL or internal Eloquent state.
- Bug fix ships with a regression test that fails before the fix.
- Use **database transactions** (`RefreshDatabase` / `LazilyRefreshDatabase`) — never hit a shared dev DB from tests.
- **Never run `vendor/bin/pest` in parallel against `cutera_test`.** The custom `RefreshTestDatabase` trait wipes and reloads the schema at startup; two concurrent pest processes (multi-terminal, parallel CI, parallel AI agents) will stomp on each other's tables mid-run and produce non-reproducible "Table X doesn't exist" failures. If you need agents in parallel, give them other work — not pest runs. If you ever need true test parallelism, plumb a per-process DB suffix (e.g. `cutera_test_<pid>`) into the connection config instead.
- Use **`Http::fake()`**, **`Queue::fake()`**, **`Mail::fake()`**, **`Event::fake()`**, **`Storage::fake()`** to isolate from external systems.
- **Architecture tests** (Pest `arch()`) enforce structural rules: no controller imports DB facade, no model imports HTTP request, services don't depend on Blade, etc.

## Observability & error handling

- **Structured logs.** Every log line carries `request_id`, `user_id` (when authed), `route`, `latency_ms`, and a stable `event` name. Use Laravel's `Log::withContext()`.
- **Throw typed exceptions.** Domain failures use named exception classes (`PaymentDeclinedException`, `PeriodLockedException`) — never generic `\Exception`. Controllers don't `try/catch \Throwable` to swallow errors.
- **Render exceptions consistently.** Map exception → HTTP status + machine `code` in one place (`bootstrap/app.php` exception handler / `Renderable`). User-facing messages never leak stack traces, SQL, file paths, or internal IDs.
- **No empty `catch`.** Every catch logs and either rethrows, returns a typed error, or schedules retry — never silent.

## Feature flags & staged rollout

- Behaviour-changing or risky features ship behind a config-driven flag (`config/features.php`) defaulting OFF. Flag has an owner and a removal date in the same PR.
- Flags are removed once the feature is stable — no permanent dead branches.

## Workflow protocol

- **For non-trivial tasks (>1 file or any logic change), read relevant files first**, summarise findings, then act. Trivial typo/format fixes can proceed directly.
- **Don't bundle unrelated module changes** in one PR/commit. Cross-cutting changes (permission renames, security patches) are explicitly cross-cutting and stated as such.
- **If unsure about business logic**, add `// CUTERA-REVIEW: <question>` inline and ask the user — never guess.
- **Show a diff summary before applying bulk changes** (>3 files or >100 lines).
- Confirm before destructive or shared-state actions (deletes, force-push, prod-like DB changes, outbound emails, 3rd-party API calls).
- Never `--no-verify` or hook-bypass without explicit user request.
- Commits small, focused, single-purpose. **Conventional Commits** format (`feat:`, `fix:`, `refactor:`, `chore:`, `docs:`, `test:`, `perf:`, `build:`, `ci:`); message body explains *why*.
- PR includes a **test plan** (verified / not verified / re-verify steps) and a **rollback plan** (revert SHA, down-migration command, or feature-flag kill switch).

## Definition of Done

A task is "done" only when **all** of the following are true:
1. Business logic preserved (or change is explicit and confirmed).
2. Server-side authz on every touched endpoint; UI gate mirrors.
3. No N+1, no unbounded `get()`, paginated lists, indexes added with new query patterns.
4. `JsonResource` whitelists fields for any new/changed API output.
5. Multi-table writes in `DB::transaction()`; side-effects via `DB::afterCommit()`.
6. Tests added/updated; bug fixes have a regression test that fails before the fix.
7. Static analysis clean; Pint clean.
8. Logs structured; no PII/secrets in logs; user-facing errors safe.
9. Mobile breakpoints (320/375/768/1024) verified for any UI change.
10. Docs updated (OpenAPI for endpoints, CLAUDE.md for new conventions, migration body for non-reversible changes).
11. Rollback plan stated.

## Code quality

- Static analysis (Larastan/PHPStan) passes at configured level — never lowered to suppress.
- No dead code, commented-out blocks, unused imports, orphan routes/views/jobs.
- Comments explain *why*, not *what*. Default: no comments.
- No speculative abstraction — abstract on the third real repetition.
- **Single source of truth per domain operation.** Every business operation (booking an appointment, recording a payment, cancelling a treatment, adjusting stock, etc.) lives in exactly one Action/Service class. Controllers, jobs, commands, console tasks, and tests call that class — they never re-implement the logic inline or copy it into another module. Duplication of business logic across call sites is a review blocker.
- **One action = one class, not one god service.** Prefer small single-purpose classes (`BookAppointmentAction`, `RecordPaymentAction`, `CancelTreatmentAction`) over fat services with many unrelated methods. Each action has one public entry point (`execute()` / `handle()` / `__invoke()`), constructor-injected dependencies, and its own Feature test. A class that accumulates unrelated responsibilities gets split, not extended.
- **Shared helpers belong in the right seam.** Cross-cutting concerns go in their named home: formatting → Resources/Presenters, validation → Form Requests, authorization → Policies, query building → Eloquent scopes or dedicated Query classes, external calls → dedicated Client classes. Don't invent `Helpers::doStuff()` bags.

## Deprecation hygiene

- Dropped column/table goes in the same PR as the replacement. Staged removals document the ticket/date in the migration body.
- No `_old`, `_temp`, `_backup`, `_new`, `_v2` suffixes in shipped code or schema.
- No deprecated Laravel/PHP APIs — replace when touching the file.
- Rename requires grep across views, routes, policies, seeders, migrations, tests, assets, and this file.
- Seeders updated alongside schema changes.
- Shipped migrations are immutable — fix-forward with a new migration.
- Abandoned/>2-year-unmaintained Composer packages flagged when touched.

## Data integrity

- All timestamps stored as UTC in the database (`timestamp` / `datetime` columns; app `timezone` = `UTC`); render in user's timezone at the edge (Blade/Resource).
- Money stored as integer minor units (paise/cents) or `decimal(N,2+)` with documented precision — never `float`/`double`. Rounding strategy stated and consistent (default: half-up at 2 decimals); intermediate calculations use higher precision, round only at boundaries.
- JSON columns reserved for opaque/non-queryable payloads. Anything filtered/sorted/joined belongs in proper columns; if a JSON path must be queryable, expose it via a **generated column** with an index.
- Destructive migrations on shared environments require a verified backup/snapshot before running, and the migration body links to the rollback procedure.
- **MariaDB 11.x exclusively** (dev on 11.8.6). Use the native `mariadb` driver and connection name. Tables `utf8mb4` charset, `utf8mb4_unicode_ci` collation (project default; switch to `utf8mb4_uca1400_ai_ci` only for new schemas if/when standardised). Use **CTEs** and **window functions** for ranking/running totals instead of self-joins. *(PHP-level `pdo_mysql` extension, `PDO::MYSQL_ATTR_*` constants, and PDO DSN `mysql:host=...` are language identifiers — required by PHP, not project-level choices.)*
- Concurrent updates to the same row use **`lockForUpdate()`** inside a transaction (pessimistic) or a `version` column with optimistic locking — never read-then-write without a guard.
- Soft-deleted models: every query that joins a soft-deletable table accounts for `deleted_at` (filter or `withTrashed()` deliberately). Composite indexes on `(deleted_at, ...)` for hot filtered queries.
- Enums backed by string values (not int) for forward-compatible storage; cast via `enum:` cast in models.
- Foreign keys explicit with `onDelete`/`onUpdate` chosen deliberately — never default-cascade without thinking through orphan/audit impact.
- Multi-row writes use bulk `insert()`/`upsert()`; never loop `Model::create()` for >50 rows.

## Project-specific

- Laravel app undergoing upgradation — adjust existing structures before introducing new patterns.
- Permissions: routes + policies authoritative; `@can` mirrors them. On any permission rename (e.g., `2026_04_12_130400_restructure_appointment_permissions.php`), audit every call site before declaring done.
- Cashflow, HR, consultancy, treatment, patient modules handle sensitive data — apply logging/PII rules strictly.
- Migrations change often here; review recent ones before writing a new one.

## Single source of truth — canonical helpers

Some questions about the business have one and only one answer-producing path. Re-use these helpers. If you find yourself rewriting their logic in a new feature, stop and call the helper instead — divergence here ships subtle financial bugs.

- **"Is the clinic operating on date X (optionally at branch Y)?" → `App\Support\OperatingDays`.**
  Composes weekly pattern (`settings.business_working_days`) + per-date overrides (`working_day_exceptions`) + date-range closures (`business_closures` + pivot). Used by InvoiceGenerationService (working-days denominator), UtilizationMetric (rota merge), DoctorDashboard (per-day revenue, days-remaining goal bar), BenchmarkCalculator (per-doctor revenue pool denominator). **Never re-implement Mon-Sat / dayOfWeek loops.** If your feature needs a per-day average, an operating-day count, or a list of "is this date open" — call `OperatingDays::datesInRange()` (org or branch-scoped) or one of the lower-level methods (`nonWorkingDates`, `closedBranchDates`). Three Mon-Sat loops drifted from this rule between 2026-04 and 2026-05-15 and were silently wrong on closure weeks; that's the failure mode to avoid.