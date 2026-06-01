#!/usr/bin/env bash
#
# Set up the local crm2 + crm3 coexistence mirror (see docs/LOCAL-COEXISTENCE.md).
# ------------------------------------------------------------------------------
# Reproduces production locally: crm2 (legacy Blade, branch `production`) as a 2nd
# Herd site + this crm3 backend, BOTH pointed at your one local dev DB, with the
# DB reconciled ADDITIVELY (dotted perms added, legacy perms KEPT) so neither app
# breaks. Run once.   bash scripts/setup-local-coexistence.sh
#
# Overrides:  CRM2_PATH=../crm2  CRM2_SITE=crm2  bash scripts/setup-local-coexistence.sh
#
# ASSUMPTION: your dev DB has NOT yet had the 2026_05 permission-rollout applied
# (those migrations are still pending). If you previously ran a plain
# `php artisan migrate`, the destructive perm migrations may already have removed
# legacy perms — reset/restore your dev DB before running this.
#
set -euo pipefail
BACKEND="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CRM2="${CRM2_PATH:-$(dirname "$BACKEND")/crm2}"
CRM2_SITE="${CRM2_SITE:-crm2}"
PHP="$(command -v php)"
COMPOSER="$(command -v composer)"

echo "Backend (crm3): $BACKEND"
echo "crm2 worktree : $CRM2  (site: ${CRM2_SITE}.test)"
echo ""

echo "==> safety: legacy permission catalog must still be intact on this DB"
INTACT=$("$PHP" "$BACKEND/artisan" tinker --execute="echo \DB::table('permissions')->where('name','patients_manage')->exists() ? 'YES' : 'NO';" 2>/dev/null | tr -d '[:space:]')
if [ "$INTACT" != "YES" ]; then
  echo "    ABORT: 'patients_manage' is missing — legacy perms were already removed on this DB."
  echo "    Restore/reset your dev DB (so the 2026_05 perm migrations are pending) and re-run."
  exit 1
fi
echo "    ok"

echo "==> 1/5 crm2 worktree on branch 'production'"
git -C "$BACKEND" fetch origin production
if git -C "$BACKEND" worktree list | grep -Eq "\[production\]$"; then
  echo "    worktree already present"
elif git -C "$BACKEND" show-ref --verify --quiet refs/heads/production; then
  git -C "$BACKEND" worktree add "$CRM2" production
else
  git -C "$BACKEND" worktree add --track -b production "$CRM2" origin/production
fi

echo "==> 2/5 composer install + runtime dirs"
"$COMPOSER" install --working-dir="$CRM2" --no-interaction --prefer-dist --no-progress
mkdir -p "$CRM2"/bootstrap/cache "$CRM2"/storage/framework/cache/data \
         "$CRM2"/storage/framework/sessions "$CRM2"/storage/framework/views \
         "$CRM2"/storage/logs "$CRM2"/storage/app/public
chmod -R ug+rwx "$CRM2"/storage "$CRM2"/bootstrap/cache

echo "==> 3/5 crm2 .env (copy crm3 env -> same DB + APP_KEY; only APP_URL differs)"
cp "$BACKEND/.env" "$CRM2/.env"
perl -pi -e "s#^APP_URL=.*#APP_URL=http://${CRM2_SITE}.test#" "$CRM2/.env"
perl -pi -e "s#^APP_ENV=.*#APP_ENV=local#" "$CRM2/.env"

echo "==> 4/5 Herd site ${CRM2_SITE}.test"
( cd "$CRM2" && herd link "$CRM2_SITE" >/dev/null && echo "    linked" )

echo "==> 5/5 additive DB reconcile (baseline destructive perm migrations, run the additive ones)"
"$PHP" "$BACKEND/artisan" tinker <<'PHP'
// Destructive / legacy-removing perm migrations — BASELINE (mark-run), never execute.
// Snapshot as of 2026-06; extend this list if new legacy-removing perm migrations land.
$baseline = [
 '2026_05_20_130000_hide_legacy_patients_group',
 '2026_05_20_140000_drop_replaced_patient_legacy_permissions',
 '2026_05_20_150000_drop_patients_upload_image_permission',
 '2026_05_20_160000_drop_patients_membership_cancel_permission',
 '2026_05_21_130000_drop_replaced_leads_legacy_permissions',
 '2026_05_22_120000_sunset_legacy_permission_assignments',
 '2026_05_22_130000_hide_legacy_consultations_group',
 '2026_05_23_120000_recategorise_permissions_to_sidebar',
 '2026_05_24_130000_hide_legacy_treatments_group',
 '2026_05_25_130000_hide_legacy_resourcerotas_group',
 '2026_05_26_130000_hide_legacy_business_closures_group',
 '2026_05_28_130000_hide_legacy_services_group',
 '2026_05_31_130000_hide_legacy_plans_group',
];
$batch = (int) \DB::table('migrations')->max('batch') + 1;
$n = 0;
foreach ($baseline as $m) {
    if (!\DB::table('migrations')->where('migration', $m)->exists()) {
        \DB::table('migrations')->insert(['migration' => $m, 'batch' => $batch]);
        $n++;
    }
}
echo 'baselined '.$n.' destructive perm migrations (rest already recorded)'.PHP_EOL;
PHP
"$PHP" "$BACKEND/artisan" migrate --force
# add_*_module flips patients_create -> status 0; restore to match prod (gate-safe).
"$PHP" "$BACKEND/artisan" tinker --execute="\DB::table('permissions')->where('name','patients_create')->update(['status'=>1]);" >/dev/null 2>&1 || true

echo ""
echo "Setup complete. Verify:  bash $BACKEND/scripts/coexistence-check.sh"
