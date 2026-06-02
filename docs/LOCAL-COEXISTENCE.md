# Local crm2 + crm3 coexistence (mirrors production)

Production runs **two apps on one shared DB**: crm2 (legacy Blade) and crm3 (SPA + api).
Every developer should mirror that locally so a crm3 change can be proven not to break crm2
*before* it ships. See the rule in `CLAUDE.md` ("crm3 must NEVER break crm2").

## One-time setup

From the backend repo root:

```bash
bash scripts/setup-local-coexistence.sh
```

This will:
1. add a **git worktree** of this repo on the `production` branch (default `../crm2`) — that's crm2,
2. `composer install` + create its runtime dirs,
3. copy your `.env` for it (same DB + APP_KEY; only `APP_URL` changes) → `crm2.test`,
4. `herd link` the crm2 site,
5. reconcile your **one** local dev DB ADDITIVELY: add the dotted perm catalog (run the
   `add_*_module_permissions` migrations) while **baselining** (never running) the destructive
   ones, so legacy perms survive and crm2 keeps working.

Overrides: `CRM2_PATH=../crm2 CRM2_SITE=crm2 bash scripts/setup-local-coexistence.sh`.

> **Pre-req:** run on a dev DB where the 2026_05 permission rollout is still *pending*. If you
> already ran a plain `php artisan migrate`, the destructive perm migrations may have removed
> legacy perms — reset/restore your dev DB first (the script aborts if `patients_manage` is gone).

## Topology after setup

| Piece | Path | Branch | Served at |
|---|---|---|---|
| crm2 (legacy Blade) | `../crm2` (worktree) | `production` | `https://crm2.test` |
| crm3 backend (api) | this repo | your branch (`shahid`/`gulraiz`/`staging`) | `https://backend.test` |
| crm3 SPA | the `frontend` repo | same | `http://localhost:5173` |
| **Shared DB** | your one local dev DB | — | both apps point here |

## The cardinal rule

The shared DB is the contract. crm2 gates on **legacy** snake_case perms (`patients_manage`);
crm3 gates on **dotted** perms (`patients.list.view`) and bridges to legacy via
`App\Support\PermissionAliasMap`. **A crm3 change must never delete/rename a legacy perm, strip
a legacy grant, or drop/rename a shared column** — crm2 has no bridge and would 403/500.

Never let a plain `php artisan migrate` run the destructive perm migrations locally
(`drop_replaced_*`, `drop_patients_*`, `sunset_legacy_permission_assignments`, `hide_legacy_*`,
`recategorise_permissions_to_sidebar`) — baseline them (the setup script does this).

## Verify after any crm3 change

```bash
bash scripts/coexistence-check.sh
```

PASS = crm2 (legacy perms present, model columns intact, multi-role gates resolve, login 200)
and crm3 (dotted gates + bridge) are both healthy on the shared DB.

Two layers of enforcement:
- **Unit (automatic, in CI + your suite):** `tests/Feature/Permissions/PermissionAliasBridgeTest.php`
  pins the legacy↔dotted bridge exhaustively, both directions.
- **Integration (run before shipping DB/perm changes):** this `coexistence-check.sh`.

## Refresh crm2 to the latest production line

```bash
git -C . fetch origin production
git -C ../crm2 reset --hard origin/production    # crm2 worktree only
```

## Tear down

```bash
herd unlink crm2
git worktree remove ../crm2
```
