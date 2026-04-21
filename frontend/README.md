# Cutera Admin SPA — `/frontend`

The new admin UI as a **decoupled React SPA** that consumes the existing
Laravel API (`/api/*`) over Sanctum bearer tokens. The legacy Blade admin at
`/admin/*` is untouched and continues to serve every page until each one is
migrated here.

> Sprint 1 ships **Login → Dashboard → Leads → Design system** as the proof
> of design direction. Other sidebar items render a "Coming soon" stub.

---

## Stack

| | |
|---|---|
| Runtime | React 19 + TypeScript 5.7 |
| Build | Vite 6 |
| Styling | Tailwind v4 (CSS-first config in `src/styles/globals.css`) |
| Components | Radix UI primitives + bespoke shadcn-style wrappers (`src/components/ui/*`) |
| Icons | Lucide React |
| Data | TanStack Query v5 |
| Routing | React Router v7 |
| Forms | React Hook Form + Zod |
| Charts | Recharts |
| Tables | TanStack Table v8 (currently used directly via state — table headless wrapper coming in Sprint 2) |

---

## Brand & tokens

Tokens live in `src/styles/globals.css` under `@theme`. Edit there — every
component picks them up automatically.

- **Brand navy** `#0F1629` / `#0B1121` — sidebar surface
- **Brand blue** `#2563EB` — primary CTAs, focus ring
- **Accent cyan** `#06B6D4` — fresh accent, highlights, active state
- **Semantic** — success / warning / danger / info (cyan)
- **Type** — Inter (variable). 13–15px body. `tracking-tight` on headings.
- **Radius** — `lg` 8px on inputs/buttons, `xl` 14px on cards, `full` on chips.
- **Shadows** — soft `shadow-xs` at rest, `shadow-sm` on hover. No heavy depth.

---

## Run it

Prereqs: Node ≥ 20, the Laravel app running locally (Herd → `:8000`).

```bash
cd frontend
npm install
npm run dev
```

The Vite dev server listens on **http://localhost:5173** and proxies `/api`
to your Laravel backend, so the browser sees a single origin (no CORS
preflight roundtrip during dev).

If your Laravel runs on a non-default port, override the proxy target:

```bash
echo 'VITE_API_PROXY_TARGET=http://cutera.test' > .env.local
```

### Production CORS (when SPA is served from a different origin)

Sprint 1 dev avoids CORS via the Vite proxy. When the SPA later runs on a
different host/port, set this in the Laravel `.env`:

```env
CORS_ALLOWED_ORIGINS=https://admin.cutera.test
```

`config/cors.php` already reads this comma-separated list. Bearer-token auth
means no `supports_credentials` flag is needed.

---

## Auth flow

1. `POST /api/login` with `{ email, password }` → returns `{ data: { ...user, api_token } }` in the standard envelope.
2. Token stored in **`sessionStorage`** under `cutera.token`. A cached user record sits at `cutera.user`.
3. Every request injects `Authorization: Bearer <token>` via `src/lib/api.ts`.
4. A `401` response triggers a global `auth:expired` event → token cleared → redirect to `/login`.

**Known follow-ups (filed for Sprint 3):**

- Tokens currently never expire (`config/sanctum.php` has `expiration => null`). CLAUDE.md mandates a finite TTL — change to a finite value once the SPA has a refresh path.
- `sessionStorage` carries a token-exfil XSS risk. Mitigation in Sprint 3 is either an httpOnly cookie bridge endpoint or an in-memory-only token + short refresh.

---

## Project layout

```
src/
├── main.tsx                    Router + QueryClient + AuthProvider mount
├── styles/globals.css          Tailwind v4 + @theme tokens + base layer
├── lib/
│   ├── api.ts                  fetch wrapper, envelope unwrap, auth header
│   ├── auth.tsx                AuthProvider + useAuth hook
│   ├── require-auth.tsx        Route guard
│   ├── query-client.ts         TanStack Query config
│   ├── format.ts               Display formatters (currency, dates, initials)
│   └── cn.ts                   className merge util (clsx + tailwind-merge)
├── components/
│   ├── ui/                     Primitives (button, card, input, table, …)
│   └── shell/
│       ├── shell.tsx           Layout: sidebar + topbar + outlet
│       ├── sidebar.tsx         8-group nav, collapse, mobile drawer, ⌘K
│       ├── sidebar-data.ts     Single source of truth for nav IA
│       ├── topbar.tsx          Breadcrumb + search trigger + user menu
│       └── (sub-components co-locate here as the shell grows)
└── routes/
    ├── login.tsx               Split-layout login wired to /api/login
    ├── dashboard.tsx           KPI strip + revenue chart + activity feed
    ├── leads.tsx               Index template — tabs, filters, table, drawer
    ├── design.tsx              Design system reference at /_design
    └── coming-soon.tsx         Catch-all friendly stub
```

---

## How to add a page

1. Create `src/routes/<name>.tsx` exporting a default React component.
2. Register it in `src/main.tsx` under the `Shell` children block.
3. If it needs data, use `useQuery` from `@tanstack/react-query` against `api.get` / `api.post`. Use `{ raw: true }` on the request when the endpoint returns a non-enveloped DataTables-style payload (e.g. `/api/leads/datatable`).
4. Mark the corresponding sidebar entry `implemented: true` in `src/components/shell/sidebar-data.ts` so its "Soon" pill drops away.
5. Mobile-first: design at 320px, then 768px, then desktop. Tap targets ≥44×44px.

---

## Testing locally against real data

The SPA expects an authenticated session. Either:

- **Use the SPA login** — `/login` accepts the same admin credentials as the legacy Blade login. The Sanctum personal access token created here is independent of the Blade web session.
- **Reuse a token** — `php artisan tinker` then `User::find(1)->createToken('dev')->plainTextToken`, paste into DevTools `sessionStorage.setItem('cutera.token', '<token>')` and refresh.

---

## Tests

Vitest unit suite covers the pure logic — formatters, API client behavior,
and sidebar IA helpers. Component / E2E tests are deferred to Sprint 3 once
we have a stable test-account convention.

```bash
npm test            # one-shot run, exits with the suite's status
npm run test:watch  # interactive, re-runs on save
npm run test:coverage  # v8 coverage → coverage/index.html
```

When adding a new pure helper or extending the API client, add a `*.test.ts`
file beside it. UI components are tested at the helper level (extracted
filters, mappers, formatters) rather than via DOM assertions for now —
keeps the suite fast and stable.

## Build for production

```bash
npm run build
```

Outputs `index.html` + hashed assets to `../public/spa/`. Sprint 2 ships a
Laravel catch-all that serves these via the `ui_v2` feature flag at
`/admin-v2/{any}`.

---

## Open questions for next sprints

- **Permissions** — sidebar currently shows every nav entry (server still gates the data). Sprint 2: `/api/me/navigation` returns the user-permitted nav tree.
- **OpenAPI** — no spec exists yet. As pages migrate, generate one with Scribe (or hand-author) so `/frontend` can consume typed clients.
- **i18n** — Laravel side already has translations; the SPA needs its own loader (lingui / i18next) before any non-English market.
- **E2E tests** — Playwright on Login → Dashboard → Leads as the smoke suite, run in CI alongside Pest.
