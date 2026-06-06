# GUIDELINES.md — coding-standards charter (Laravel backend)

The coding-standards charter for Claude Code in this repo. Same charter as the SPA
(`../frontend/GUIDELINES.md`); this file is the **backend companion**. **Parts A
(principles) and B (security) below are the shared cross-repo standard — byte-identical
to the frontend's copy and machine-enforced by `scripts/check-guidelines-sync.sh`, so
they read for both apps and mention both stacks.** Part C then covers the **PHP 8.4 /
Laravel 12** conventions (the frontend's copy carries its React/TypeScript ones instead).
`CLAUDE.md` holds project context + hard invariants and points here; **read this before
writing code.** Companion: `TESTING.md` (the test-sync system).

> **Editing Parts A or B?** Make the identical edit in `../frontend/GUIDELINES.md` too —
> the sync guard fails the gate if the two shared blocks drift apart.

---

<!-- GUIDELINES-SYNC:BEGIN -- Parts A + B are the shared cross-repo standard: byte-identical to ../frontend/GUIDELINES.md, enforced by scripts/check-guidelines-sync.sh. Edit BOTH. -->
# Part A — Principles (every change)

## A1. Standing disciplines
**Stay in scope.** `[B]` Smallest change that does the job; match the surrounding idiom; no
drive-by edits, no unrelated churn, no dead code left behind. Spot something else worth doing?
Flag it — don't do it. *(I tend to "improve" code you didn't ask about.)*

**Verify before claiming.** `[B]` Never report "done/works" without the test/build passing;
never cite a symbol, endpoint, file, or column I haven't confirmed exists; never state a memory
as fact without re-checking. *(I assume APIs exist and report success I never ran.)*

**Report honestly.** `[B]` State failures with the real output; say plainly what was skipped or
left incomplete; never empty-catch an error or gloss over one in a summary.

**Write current, not remembered.** `[B]` + `[M]` partly My training over-weights older patterns,
so before writing stack-specific code I check the installed version (`package.json` /
`composer.json`) and use *that version's* current idiom. Never a pattern from memory without
confirming it's current; never a superseded API; never a method I haven't confirmed exists;
prefer a newer stable feature when it's clearly better. *(A human googles the current docs; I
pattern-match to training — so verifying against the installed stack is mandatory. This rule is
what keeps the charter evergreen as the stack upgrades.)*

## A2. Reuse — and use the platform
**Principle.** Find the existing version and reuse or extend it before writing anything new.
Create new only when nothing equivalent exists — and never force two unlike things to share
code (a wrong abstraction is worse than the duplication it removes).

**"Existing" includes the stack, not just our repo.** `[B]` *I generate from first principles
instead of recalling that PHP 8.4 / Laravel 12 / React 19 (or TanStack Query/Table, RHF, Zod,
Radix, `Intl`) already provides it* — writing a `foreach` where `array_find` / a Collection
method fits, a custom currency formatter instead of `Intl.NumberFormat` / `Number::currency()`,
hand-rolled caching instead of React Query, a bespoke validator instead of Zod/FormRequest, or
manual focus-trapping instead of Radix. Reach for the **highest-level tool that fits: framework
helper > installed library > language stdlib > hand-rolled.** Never reinvent collection/string
helpers, date math, number/currency/locale formatting, validation, caching/retry, pagination,
auth/hashing/encryption, UUID/ULID, query building, or a11y primitives.

**The decision test.** "If the rule changed, would both copies have to change together, for the
same reason?" **Yes** → same thing → reuse one unit (duplicating it = the *reinvention* breach).
**No** → they only look alike → keep them separate (merging them = the *wrong-abstraction* breach).

**Checklist, before writing code:** (1) **search our repo *and* the stack** (`grep -rn`,
`src/lib/`, `src/components/ui/`, `app/Services/`, `app/Support/`; and "does the language /
framework / an installed lib already do this?"); (2) found → reuse, or extend with a
variant/param — don't copy; (3) not found → write once, generalize only at the **3rd** use
(rule of three), never pre-abstract; (4) must duplicate → log a one-line reason (Enforcement,
top); (5) before finishing → review for the same logic written twice in different words.

| Layer | Rule | |
|---|---|---|
| Design tokens (color, spacing, type, icons) | the one shared token; never a raw value | `[M]` |
| UI primitives (button, input, dialog, drawer, card, **date cell**, list shell, table) | reuse from `src/components/ui/`; never re-roll | `[M]`+`[B]` |
| Cross-cutting helpers (`api`, `auth`, `toast`, `cn`, `permissions`, `format`, `url`, `date`) | use the existing one | `[M]`+`[B]` |
| Types / Zod schemas · models / DTOs | one shape per concept; mirror the API, don't redefine | `[B]` |
| Feature logic / hooks (FE) · Services / Support (BE) | reuse single-source modules; extract at the 3rd use | `[B]` |

Where shared things live: `src/lib/*.ts` (pure fns/hooks), `src/components/ui/*.tsx` (generic
primitives), `src/components/<feature>/` (feature-scoped — lift to `ui/` only when reused across
unrelated features). Inventory + open consolidation list: memory `feedback_reusable_over_duplication`.

## A3. Efficiency & simplicity `[B]` (some `[M]`)
- **Simplest correct solution.** The least code that solves the *actual* problem. No speculative
  generality (YAGNI). The most optimal code is usually the simplest that's correct.
- **Readability is part of optimal.** Clear names, small focused functions, low cognitive load —
  code is read far more than written. Optimise for "easy to change/delete," not "clever."
- **Right approach before micro-tuning.** Pick the right algorithm + data structure (the Big-O
  that matters) and let the stack do the work; don't hand-tune a loop the framework optimises.
- **No avoidable waste:** kill N+1, don't refetch cached data, don't re-render needlessly, don't
  load a list just to count it. Code-split heavy libs; trim bundle/payload; memoize only where it
  measurably helps. But **no premature micro-optimization**, and never trade readability for a
  speed-up you can't measure. Perf invariants: `project_dashboard_overview_perf`,
  `project_plans_perf_followups`.

## A4. Deprecated / legacy code `[B]`
Two different things — never confuse them:
- **My own leftovers** (commented-out lines, unused imports, a helper I just replaced) → remove
  them in the same change.
- **The repo's `@deprecated` / legacy / Blade code** → **do NOT remove it.** crm2 is live on the
  shared DB and depends on it; removal is **gated on crm2 being disconnected**, a deliberate
  future decision. (`project_blade_crm2_coexistence`, `feedback_no_new_legacy_dependencies`,
  `feedback_crm3_must_not_break_crm2`.)

---

# Part B — Security (all layers — the 2026-06 audit, made regression-proof)

Each rule is a "never reintroduce this," grounded in real audit fixes. *I add an endpoint or
query from scratch and silently bring back a class the audit closed* (an unscoped lookup = IDOR;
a bare `exists:` = cross-tenant FK injection). All patient/staff data is **HIPAA-grade PII.**
Security-sensitive code ships with a `tests/Feature/Security/` test (a fix writes the failing
test first). The security-analysis workstream owns this area — this encodes *shipped* standards;
coordinate before changing security *behavior*.

**Code / application** `[B]` (+ `[M]` tests)
- **Tenant-scope every lookup (IDOR):** any query on a client-supplied id is scoped to the caller's `account_id` and excludes soft-deletes. **Never an unscoped `findOrFail($id)`.**
- **Authorize everything (defence in depth):** permission gate + `FormRequest::authorize` + route middleware. No write route without a check; **no GET mutates.**
- **Mass-assignment allowlists:** allowlist editable columns; never let the client set commission / eligibility / amount fields. Pin the FormRequest contract with a tripwire test.
- **Separation of duties:** the creator can't approve / cancel / delete their own financial record.
- **Race safety:** `lockForUpdate` inside the transaction for any read-modify-write on money/state.
- **Idempotency:** payment/financial writes honor an Idempotency-Key (`EnsureIdempotency`).
- **Secrets & PII:** none in source or logs; `$hidden` password/remember_token; never SELECT or serialize password hashes, CNIC, email, or DOB unless gated; minimize resource + search fields.

**API** `[M]` partly + `[B]`
- **FK validation:** `Rule::exists()->where('account_id',$x)->whereNull('deleted_at')` — never a bare `exists:`.
- **Input validation at the boundary** (FormRequest): strict types; amounts `integer|min:1|max:…`; reject negative / fractional / scientific-notation / oversized.
- **Rate-limit:** login 10/min; tight on search / export / datatable + all payment writes; generous default elsewhere.
- **CSRF** enforced for cookie auth (no blanket `/api/*` bypass).
- **Sort/filter allowlists** — only known columns from client `orderBy`/filter params.
- **Enumeration-safe errors** — one generic 401; mask 5xx before surfacing.
- **Upload hardening** — validate MIME/size; no SVG in image uploads; sanitize filenames; store outside the webroot.

**Database** `[M]` partly + `[B]`
- **Parameterized queries only** (Eloquent/bindings) — never string-built SQL. *(SQL-injection home; C4 points here.)*
- Query-layer `account_id` scoping backed by DB FK constraints; always `whereNull('deleted_at')`.
- **Immutable audit log** — sensitive actions logged; audit tables append-only (DB triggers block UPDATE/DELETE — keep them).
- **Encryption-at-rest follows the explicit decision** (`project_cnic_bank_account_no_encryption_decision`; activity-log description is encrypted) — coordinate before changing a cast.
- Least-privilege DB credentials; the browser never touches the DB (API only).

**Server / infra** `[B]`
- Security-headers middleware + the SPA `.htaccess`: CSP, HSTS, X-Frame-Options/`frame-ancestors`, X-Content-Type-Options, Referrer-Policy — don't weaken them.
- HTTPS only (`https://api.cutera.pk`); never commit or expose secrets/`.env`.
- The server `.htaccess` (Basic-Auth gate + headers) is **preserved** on deploy — never overwrite it; deploy via the gated pipeline, never ad-hoc SSH.
- No directory listing; deny dotfiles.

**Web / browser** `[M]` partly + `[B]`
- **XSS:** no `dangerouslySetInnerHTML` without sanitizing; never inject unescaped server data.
- `rel="noopener noreferrer"` on `target="_blank"`; validate URL scheme via `safeHref`; `encodeURIComponent` user values in URLs.
- Auth tokens never in `localStorage` (Sanctum httpOnly cookie default; `sessionStorage` for bearer/passport); keep the client bundle secret-free.
- Client-side permission checks are **UX only** (see C5) — the server is the authority; clickjacking covered by `frame-ancestors`.

**Cross-cutting** `[M]` partly + `[B]`
- `composer audit` + `npm audit` clean (0 advisories); don't add an unmaintained package. Deny by default; least privilege; when unsure if something is sensitive, treat it as sensitive.
<!-- GUIDELINES-SYNC:END -->

---

# Part C — This-stack conventions (Laravel 12 / PHP 8.4)

## C1. Controllers, requests & responses
- **Thin controllers.** A controller method validates (via a FormRequest), delegates to a Service, and returns a Resource — no business logic, no fat closures. Orchestration lives in `app/Services/`.
- **One endpoint per concept.** Extend an endpoint with a parameter; never fork a near-duplicate route. One uniform response envelope `{ success, data, errors, message }` — never a bespoke shape per endpoint (the SPA's `api.ts` unwraps this; changing the shape breaks it).
- **Validation at the boundary** via FormRequest (`authorize()` + `rules()`), never inline `$request->validate()` for anything non-trivial. FK rules use `Rule::exists()->where('account_id')` (Part B).
- **Shape output with API Resources** — never `return $model->toArray()` raw; control exactly which columns leave the boundary (PII minimization, Part B).

## C2. Models, services & domain logic
- **Models stay lean:** relationships, casts, scopes, accessors — not business workflows. Heavy logic → a Service; reusable domain questions → `app/Support/` (e.g. `OperatingDays` is the single source for "is the clinic open on X?").
- **Casts & Enums:** use `app/Casts/` and `app/Enums/` for typed columns; union-style string constants → a backed Enum, not loose strings.
- **Observers/Events** for lifecycle side-effects (e.g. `MembershipObserver` cascades referral expiry) — keep them idempotent and well-scoped; don't bury money/state mutations in an observer where a transaction belongs.
- **Computed values are display overlays**, not baked into stored/cached columns — unless deliberately cached with an invalidation path (`project_cashflow_inventory_overlay_coexistence`).

## C3. PHP / typing
- Strict types where the file already uses them; type every method signature (params + return); typed properties over loose `mixed`.
- No `@phpstan-ignore` / suppression to silence a real type problem — fix the type. Prefer Enums and value objects over magic strings/arrays.
- Use PHP 8.4 idioms the installed version ships (readonly props, enums, first-class callable syntax, `match`) over older equivalents (A1).

## C4. Database & backend engineering `[B]` (some `[M]`)
*I don't hold the schema, indexes, or data volumes — so unchecked I add columns without indexes,
pick wrong types, miss an FK, write N+1, and assert performance I never measured.*
- **Read before you change.** Read the actual table — columns, types, indexes, FKs/constraints — don't infer from the model.
- **Money & precision:** never `float`/`double` for money — `DECIMAL` (or integer minor units); match the existing money columns. (tax / bundle / price math.)
- **Schema:** 3NF by default; right-size types (no reflex `VARCHAR(255)`); `utf8mb4`; UTC datetimes; explicit FK constraints; enforce invariants with `UNIQUE` (not app code alone).
- **Indexing:** index every FK and every `WHERE`/`ORDER BY`/`JOIN` column; composite order matters (equality / most-selective first); confirm with `EXPLAIN` — never claim an index helps without it; don't over-index write-heavy tables.
- **Query performance:** eager-load (no N+1); aggregate in SQL not PHP; select only needed columns; keyset/cursor pagination over deep `OFFSET`; `chunk()` big jobs.
- **Transactions & integrity:** wrap multi-row money/state invariants in a transaction; lock where a race matters; financial ops idempotent (Part B).
- **Laravel hygiene:** FormRequest validation + mass-assignment guard + parameter binding (the security angle is Part B); slow work → queues (`app/Jobs/`); no deprecated Eloquent/helper APIs (A1).
- **Migrations own all schema** (reversible, additive; backfill separate from DDL; never ad-hoc prod SQL).
- **Additive-only vs the shared DB (crm2) — canonical rule:** never rename/drop a shared column, route, or response shape; `scripts/coexistence-check.sh` must PASS. Destructive ops (`dropColumn`/`renameColumn`/`Schema::drop`) are **machine-flagged** by the guidelines gate — waive only if confirmed crm2-safe. PII-at-rest per `project_cnic_bank_account_no_encryption_decision`. The local `crm` DB is always ≥ prod, never behind.

## C5. Permissions
Permission checks gate behavior server-side and are the **security authority** (the SPA's
client-side checks are UX hints only). Authorize via policy/gate + FormRequest + middleware
(Part B). Never delete/rename a legacy permission or strip a legacy role grant — crm2 reads the
same permission rows from the shared DB (`feedback_crm3_must_not_break_crm2`).

---

# Part D — Do / Don't quick list

**Do:** keep controllers thin (validate → service → resource); reuse `app/Services` /
`app/Support` / `app/Rules` / Carbon / `Number` / framework helpers; author all schema via Laravel
migrations (reversible, additive vs the shared crm2 DB); run `scripts/coexistence-check.sh` before
shipping any DB/permission/shared-endpoint change; keep responses on the one uniform envelope.

**Don't:** put business logic in a controller or model; fork a near-duplicate route or invent a
bespoke response shape; write string-built SQL; run an unscoped `findOrFail` (IDOR) or a bare
`exists:` (cross-tenant FK); let a GET mutate; rename/drop a shared column/route/permission crm2
depends on; run ad-hoc prod SQL in place of a migration; add an unmaintained or unneeded package —
the stack is the stack.
