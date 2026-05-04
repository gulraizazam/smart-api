# Listing-API Performance Baseline

Phase 0 of the listing-API optimization plan. This file captures the pre-optimization timings of every list endpoint so each per-module PR can be measured against an objective bar.

## How to capture

1. In `.env`, set `LOG_SLOW_QUERIES=true`. Optionally tune `LOG_SLOW_QUERIES_THRESHOLD_MS` (default 250) and `LOG_SLOW_QUERIES_REQUEST_MS` (default 500).
2. Restart any long-running PHP processes (`php artisan optimize:clear` if config is cached).
3. Open the SPA at `http://localhost:5173/`, log in as an operator with realistic data scope (an account that has many patients, leads, and appointments).
4. For each module in the table below:
   - Click into it (default page=1, no filters). Note the `X-Response-Time-Ms` response header from the browser's network panel.
   - Apply one common filter (the one a real operator uses daily — search by name, status filter, etc.). Note the header again.
   - Tail `storage/logs/slow-queries.log` for any per-query lines >250ms tied to that route.
5. Fill in the row. Use median of 3 hits to smooth jitter.
6. When done, set `LOG_SLOW_QUERIES=false`. The flag is cheap when off but no need to leave it on.

## Result columns

- **Cold ms** — first hit after a cache flush (`php artisan cache:clear && php artisan config:clear`). Worst-case load.
- **Warm ms** — median of 3 subsequent hits.
- **Filter ms** — warm timing with the common filter applied.
- **Slow queries** — count of per-query log lines >250ms attributed to this route, plus the worst SQL fragment in 1-2 words ("members join", "phone whereHas", etc.).
- **Notes** — anything specific (e.g. "withCount triggers extra query", "huge JSON column in select").

| # | Module | Endpoint | Cold ms | Warm ms | Filter ms | Slow queries | Notes |
|---|--------|----------|--------:|--------:|----------:|:-------------|:------|
| 1 | appointments calendar | `GET  /api/appointments/scheduled` | | | | | |
| 2 | appointments list | `GET  /api/appointments` | | | | | |
| 3 | patients | `POST /api/patients/datatable` | | | | | |
| 4 | leads | `POST /api/leads/datatable` (or equivalent) | | | | | |
| 5 | users | `POST /api/users/datatable` | | | | | |
| 6 | doctors | `POST /api/doctors/datatable` | | | | | |
| 7 | hr-employees | `POST /api/hr-employees/datatable` | | | | | |
| 8 | hr-recruitment | `POST /api/hr-recruitment/datatable` | | | | | |
| 9 | bundles | `POST /api/bundles/datatable` | | | | | |
| 10 | services | `POST /api/services/datatable` | | | | | |
| 11 | discounts | `POST /api/discounts/datatable` | | | | | |
| 12 | packages | `POST /api/packages/datatable` | | | | | |
| 13 | plans | `POST /api/plans/datatable` | | | | | |
| 14 | roles | `POST /api/roles/datatable` | | | | | |
| 15 | regions | `GET  /api/regions` | | | | | |
| 16 | cities | `GET  /api/cities` | | | | | |
| 17 | towns | `GET  /api/towns` | | | | | |
| 18 | centres | `GET  /api/centres` | | | | | |
| 19 | centre-targets | `GET  /api/centre-targets` | | | | | |
| 20 | lead-sources | `GET  /api/lead_sources` | | | | | |
| 21 | lead-statuses | `GET  /api/lead_statuses` | | | | | |
| 22 | appointment-statuses | `GET  /api/appointment_statuses` | | | | | |
| 23 | machine-types | `GET  /api/machine_types` | | | | | |
| 24 | resources | `GET  /api/resources` | | | | | |
| 25 | sms-templates | `GET  /api/sms_templates` | | | | | |
| 26 | operator-settings | `GET  /api/operator_settings` | | | | | |
| 27 | user-types | `GET  /api/user_types` | | | | | |
| 28 | invoices | `GET  /api/invoices` | | | | | |
| 29 | settings | `GET  /api/settings` | | | | | |

## After-numbers (filled in per Phase 1 PR)

After each per-module optimization PR ships, copy the row from above into this section and fill in the new numbers, with a one-line summary of what changed.

| # | Module | Cold ms | Warm ms | Filter ms | Slow queries | What changed | PR |
|---|--------|--------:|--------:|----------:|:-------------|:-------------|:---|

## How the instrumentation works

- `App\Providers\AppServiceProvider::registerSlowQueryLogger()` — `DB::listen()` callback that writes per-query lines >250ms (configurable) to `storage/logs/slow-queries.log`. Activated only when `LOG_SLOW_QUERIES=true`.
- `App\Http\Middleware\LogSlowRequests` — appended to the `api` middleware group in `bootstrap/app.php`. Adds `X-Response-Time-Ms` on every API response and writes a per-request summary line for requests >500ms (configurable).

Both pieces are temporary; they get removed in Phase 2 once optimization work is done. They are zero-cost when the env flag is off.
