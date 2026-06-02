#!/usr/bin/env bash
#
# Cutera local coexistence check  (committed — see docs/LOCAL-COEXISTENCE.md)
# ---------------------------------------------------------------------------
# crm2 (legacy Blade, branch `production`) and crm3 (this backend, your branch)
# share ONE local DB, mirroring production. Run AFTER any crm3 change that
# touches the shared DB / permissions to confirm it did NOT break crm2.
# Exit 0 = both green; non-zero = something broke.
#
#   bash scripts/coexistence-check.sh
#
# Paths/hostnames are derived; override if your Herd setup differs:
#   CRM2_PATH=../crm2 CRM2_URL=https://crm2.test CRM3_URL=https://backend.test \
#     bash scripts/coexistence-check.sh
#
set -u
BACKEND="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CRM2="${CRM2_PATH:-$(dirname "$BACKEND")/crm2}"
CRM2_URL="${CRM2_URL:-https://crm2.test}"
CRM3_URL="${CRM3_URL:-https://backend.test}"
PHP="$(command -v php || echo php)"
rc=0

if [ ! -d "$CRM2" ]; then
  echo "crm2 worktree not found at: $CRM2"
  echo "Run scripts/setup-local-coexistence.sh first (or set CRM2_PATH)."
  exit 2
fi

echo "=== HTTP boot ==="
c2=$(curl -skS -o /dev/null -w '%{http_code}' "$CRM2_URL/login" 2>/dev/null)
c3=$(curl -skS -o /dev/null -w '%{http_code}' -H 'Accept: application/json' "$CRM3_URL/api/user" 2>/dev/null)
printf '  crm2  %s/login        -> %s (want 200)\n' "$CRM2_URL" "$c2"
printf '  crm3  %s/api/user  -> %s (want 401)\n' "$CRM3_URL" "$c3"
[ "$c2" = 200 ] || { echo "  >> crm2 login NOT 200"; rc=1; }

echo "=== crm2 contract (legacy perms present + crm2 model columns exist + multi-role gates) ==="
out2=$("$PHP" "$CRM2/artisan" tinker 2>/dev/null <<'PHP'
$fail = [];
// 1. Legacy umbrella perms crm2 gates on must still EXIST (catches a crm3 drop_* migration).
$contract = ['patients_manage','plans_manage','services_manage','consultations_manage','treatments_manage','leads_manage','invoices_manage'];
foreach ($contract as $c) { if (!\DB::table('permissions')->where('name', $c)->exists()) { $fail[] = 'perm_gone='.$c; } }
// 2. Shared SCHEMA contract: every column in crm2's own models' $fillable must still exist
//    (catches a crm3 migration dropping/renaming a column crm2 reads).
$models = [\App\Models\Patients::class, \App\Models\Leads::class, \App\Models\PackageAdvances::class, \App\Models\Services::class, \App\Models\Packages::class];
foreach ($models as $cls) {
    $m = new $cls();
    $t = $m->getTable();
    if (!\Illuminate\Support\Facades\Schema::hasTable($t)) { $fail[] = 'table_gone='.$t; continue; }
    foreach ($m->getFillable() as $col) { if (!\Illuminate\Support\Facades\Schema::hasColumn($t, $col)) { $fail[] = 'col_gone='.$t.'.'.$col; } }
}
// 3. Multi-role legacy gate resolution (catches a stripped legacy grant per role).
foreach (['Administrator','Finance','CSR','FDM'] as $rn) {
    $u = \App\Models\User::whereHas('roles', fn($q)=>$q->where('name', $rn))->first();
    if (!$u) { continue; }
    \Illuminate\Support\Facades\Auth::login($u);
    $held = $u->getAllPermissions()->filter(fn($p)=>!str_contains($p->name, '.'))->take(3)->pluck('name');
    foreach ($held as $p) { if (!\Illuminate\Support\Facades\Gate::allows($p)) { $fail[] = 'gate_denied='.$rn.':'.$p; } }
    \Illuminate\Support\Facades\Auth::logout();
}
$legacy = \DB::table('permissions')->where('name', 'not like', '%.%')->count();
echo count($fail) ? ('CRM2_FAIL '.implode(' ', $fail)) : ('CRM2_OK legacy_perms='.$legacy.' umbrellas_present schema_intact multi_role_gates_resolve');
echo PHP_EOL;
PHP
)
echo "  $(echo "$out2" | grep -E 'CRM2_(OK|FAIL)')"
echo "$out2" | grep -q CRM2_OK || rc=1

echo "=== crm3 dotted gate resolution + bridge ==="
out3=$("$PHP" "$BACKEND/artisan" tinker 2>/dev/null <<'PHP'
try {
    $u = \App\Models\User::whereHas('roles', fn($q)=>$q->whereNotIn('name', ['Super-Admin','Super Admin']))->first();
    \Illuminate\Support\Facades\Auth::login($u);
    $gates = ['patients.list.view','plans.list.view','services.list.view','leads.list.view'];
    $pass = count(array_filter($gates, fn($g)=>\Illuminate\Support\Facades\Gate::allows($g)));
    $alias = \App\Support\PermissionAliasMap::aliasesFor('patients_manage');
    $dotted = \DB::table('permissions')->where('name', 'like', '%.%')->count();
    echo 'CRM3_OK dotted_perms='.$dotted.' gates_pass='.$pass.'/'.count($gates).' bridge='.(count($alias) ? 'on' : 'OFF').PHP_EOL;
} catch (\Throwable $e) { echo 'CRM3_FAIL '.$e->getMessage().PHP_EOL; }
PHP
)
echo "  $(echo "$out3" | grep -E 'CRM3_(OK|FAIL)')"
echo "$out3" | grep -q CRM3_OK || rc=1

echo ""
[ "$rc" = 0 ] && echo "RESULT: PASS - crm2 + crm3 both healthy on the shared DB" || echo "RESULT: FAIL - see above"
exit $rc
