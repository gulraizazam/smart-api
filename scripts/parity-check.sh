#!/usr/bin/env bash
#
# Cutera PROD <-> LOCAL schema parity check   (committed — read-only)
# ---------------------------------------------------------------------------
# Closes the gap that local-first development can't see: that PROD actually
# MATCHES local after a deploy.
#
# During cutover, several crm3 migrations were BASELINED on prod (recorded in
# the `migrations` table) WITHOUT their schema being applied — so `migrate`
# skips them forever and the columns are silently missing on prod. A plain
# "does the column exist" diff also MISSED `cash_transfers.method` being
# NOT NULL on prod but nullable locally, which 500'd every branch->branch
# cash movement. So this script diffs a full COLUMN SIGNATURE
# (table|column|type|nullable|default), not just column presence.
#
# 100% READ-ONLY: only information_schema SELECTs + one curl smoke. It NEVER
# writes to either database and never runs migrate / cache / config.
#
# Usage (local-only — prints the prod command to run by hand):
#   bash scripts/parity-check.sh
#
# Usage (full diff — supply read-only prod access; SSH read access is
# pre-authorized, see docs / memory feedback_prod_ssh_access_step_by_step):
#   PARITY_PROD_SSH='-p 65002 -i '"$HOME"'/.ssh/cuteraesthetics_hostinger u572390775@156.67.66.146' \
#   PARITY_PROD_ARTISAN='cd ~/domains/api.cutera.pk/crm && /opt/alt/php84/usr/bin/php artisan' \
#     bash scripts/parity-check.sh
#
# Exit 0 = PASS (no attribute drift, no baselined-not-applied gap, crm2 200).
# Local-only columns (changes not yet deployed) are REPORTED, not failed.
#
set -u
BACKEND="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PHP="$(command -v php || echo php)"
CRM2_URL="${CRM2_URL:-https://crm2.cutera.pk}"
rc=0

# Single-line tinker payloads (single-line so they survive being piped over
# SSH — multi-line heredocs get mangled by the remote REPL).
SIG_PHP='echo "__SIG__".PHP_EOL;foreach(DB::select("SELECT CONCAT_WS(chr(124),TABLE_NAME,COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,IFNULL(COLUMN_DEFAULT,char(60,110,111,110,101,62))) sig FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME,COLUMN_NAME") as $r){echo $r->sig.PHP_EOL;}'
MIG_PHP='echo "__MIG__".PHP_EOL;foreach(DB::table("migrations")->orderBy("migration")->pluck("migration") as $m){echo $m.PHP_EOL;}'
DBN_PHP='echo "DB=".DB::connection()->getDatabaseName().PHP_EOL;'

# Columns that MUST be present on prod (from project_prod_schema_baseline_gap
# + this round's additive work). A column here that is absent on prod = a
# baselined-not-applied gap (or an undeployed change) — surfaced explicitly.
BASELINE_COLS="package_advances.order_id appointments.meta_booked_sent appointments.meta_arrived_sent stocks.sale_price bundles.pricing_mode base_discount_services.is_category bundle_has_services.id staff_advances.advance_date staff_returns.return_date staff_transfers.transfer_date package_bundles.account_id memberships.parent_membership_code"

# Direct invocation (not eval) so the Herd php path's space survives.
run_local() { "$PHP" "$BACKEND/artisan" tinker --execute="$1" 2>/dev/null; }
run_prod()  { ssh $PARITY_PROD_SSH "$PARITY_PROD_ARTISAN tinker --execute='$1'" 2>/dev/null; }
only_sig()  { awk 'f && /^[A-Za-z0-9_]+\|/{print} /__SIG__/{f=1}'; }
only_mig()  { awk 'f && /^[0-9]/{print} /__MIG__/{f=1}'; }

echo "=== local DB ==="
run_local "$DBN_PHP" | grep -E '^DB='

LOCAL_SIG="$(run_local "$SIG_PHP" | only_sig | sort)"
LOCAL_MIG="$(run_local "$MIG_PHP" | only_mig | sort)"
echo "  local columns: $(printf '%s\n' "$LOCAL_SIG" | grep -c '|')   local migrations: $(printf '%s\n' "$LOCAL_MIG" | grep -c .)"

if [ -z "${PARITY_PROD_SSH:-}" ]; then
  echo ""
  echo "PROD access not provided (PARITY_PROD_SSH unset) — local signature computed only."
  echo "To complete the diff, run the prod side read-only and re-run with PARITY_PROD_SSH/PARITY_PROD_ARTISAN set."
  echo "Manual prod signature command:"
  echo "  ssh <prod> \"<artisan> tinker --execute='${SIG_PHP}'\""
  echo ""
  echo "RESULT: PARTIAL - local-only (no prod comparison this run)"
  exit 0
fi

echo "=== prod DB (read-only) ==="
run_prod "$DBN_PHP" | grep -E '^DB='
PROD_SIG="$(run_prod "$SIG_PHP" | only_sig | sort)"
PROD_MIG="$(run_prod "$MIG_PHP" | only_mig | sort)"
if [ -z "$PROD_SIG" ]; then
  echo "  >> could not read prod signature (check PARITY_PROD_SSH / PARITY_PROD_ARTISAN)"
  exit 2
fi
echo "  prod columns: $(printf '%s\n' "$PROD_SIG" | grep -c '|')   prod migrations: $(printf '%s\n' "$PROD_MIG" | grep -c .)"

# Reduce signatures to table.column keys for presence diffs.
local_keys() { printf '%s\n' "$1" | awk -F'|' '{print $1"."$2}'; }
LKEYS="$(local_keys "$LOCAL_SIG")"
PKEYS="$(local_keys "$PROD_SIG")"

echo "=== columns present LOCAL but missing on PROD (changes to deploy / baseline gaps) ==="
LOCAL_ONLY="$(comm -23 <(printf '%s\n' "$LKEYS") <(printf '%s\n' "$PKEYS"))"
if [ -n "$LOCAL_ONLY" ]; then printf '  %s\n' $LOCAL_ONLY; else echo "  (none)"; fi

echo "=== columns present on PROD but not LOCAL (legacy crm2 — informational) ==="
PROD_ONLY_N="$(comm -13 <(printf '%s\n' "$LKEYS") <(printf '%s\n' "$PKEYS") | grep -c .)"
echo "  count: $PROD_ONLY_N"

echo "=== ATTRIBUTE DRIFT on shared columns (type / nullable / default) — FAIL ==="
# For columns present on BOTH, compare the full signature line. A key whose
# full signature differs is drift (the cash_transfers.method NOT-NULL class).
SHARED_KEYS="$(comm -12 <(printf '%s\n' "$LKEYS" | sort -u) <(printf '%s\n' "$PKEYS" | sort -u))"
drift=0
while IFS= read -r key; do
  [ -z "$key" ] && continue
  lt="$(printf '%s\n' "$LOCAL_SIG" | awk -F'|' -v k="$key" '$1"."$2==k{print; exit}')"
  pt="$(printf '%s\n' "$PROD_SIG" | awk -F'|' -v k="$key" '$1"."$2==k{print; exit}')"
  if [ "$lt" != "$pt" ]; then
    echo "  DRIFT $key"
    echo "    local: $lt"
    echo "    prod : $pt"
    drift=$((drift+1))
  fi
done <<< "$SHARED_KEYS"
[ "$drift" = 0 ] && echo "  (no drift)" || rc=1

echo "=== baseline-gap probe: required columns must EXIST on prod ==="
for col in $BASELINE_COLS; do
  if printf '%s\n' "$PKEYS" | grep -qx "$col"; then
    : # present
  else
    in_ledger=""
    printf '%s\n' "$LOCAL_ONLY" | grep -qx "$col" && in_ledger="(local-only — verify it's a pending deploy, not baselined-not-applied)"
    echo "  MISSING on prod: $col $in_ledger"
    rc=1
  fi
done

echo "=== migrations ledger: on disk but NOT recorded on prod (to deploy) ==="
DISK_MIG="$(cd "$BACKEND/database/migrations" && ls *.php 2>/dev/null | sed 's/\.php$//' | sort)"
comm -23 <(printf '%s\n' "$DISK_MIG") <(printf '%s\n' "$PROD_MIG") | sed 's/^/  + /' | head -40
echo "=== migrations recorded on prod but NOT on disk (investigate) ==="
comm -13 <(printf '%s\n' "$DISK_MIG") <(printf '%s\n' "$PROD_MIG") | sed 's/^/  ? /' | head -40

echo "=== crm2 smoke ==="
# crm2 is intentionally Basic-Auth locked for maintenance (same creds as the
# crm3 gate). 200 (authenticated, app healthy behind the gate) or 401 (gate up =
# box alive) both mean crm2 is fine; only an unreachable box (000/5xx) is a real
# failure. Authenticate when the gate creds are present for the stronger check.
CRM2_GATE_AUTH="${CRM2_GATE_AUTH:-${CRM3_GATE_AUTH:-}}"
if [ -n "$CRM2_GATE_AUTH" ]; then
  c2="$(curl -skS -u "$CRM2_GATE_AUTH" -o /dev/null -w '%{http_code}' "$CRM2_URL/login" 2>/dev/null)"
else
  c2="$(curl -skS -o /dev/null -w '%{http_code}' "$CRM2_URL/login" 2>/dev/null)"
fi
case "${c2:-000}" in
  200) echo "  $CRM2_URL/login -> 200 (crm2 healthy behind the maintenance gate)" ;;
  401) echo "  $CRM2_URL/login -> 401 (crm2 up; Basic-Auth maintenance lock — OK)" ;;
  *)   echo "  $CRM2_URL/login -> ${c2:-000} (crm2 looks DOWN)"; rc=1 ;;
esac

echo ""
[ "$rc" = 0 ] && echo "RESULT: PASS - no drift, no baseline gap, crm2 healthy" || echo "RESULT: FAIL - see DRIFT / MISSING / crm2 above"
exit $rc
