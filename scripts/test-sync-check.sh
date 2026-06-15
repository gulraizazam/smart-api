#!/usr/bin/env bash
# =============================================================================
# test-sync-check.sh  --  missing-test / orphan / focus gate (Laravel backend)
# =============================================================================
# The backend-resident half of the test-sync system (the SPA's
# .claude/hooks/test-sync-guard.sh checks both repos locally; in CI each repo is
# checked out alone, so this runs the same idea on the committed diff here).
#
# Rule (calibrated to our naming): backend tests are named by BEHAVIOUR, not by
# class, so we do NOT demand a name-matched FooTest.php. Instead:
#   * a change to app/**/*.php (logic) must ship WITH some tests/**/*Test.php change
#   * CI diff-coverage (composer test:diff-cover) verifies the changed LINES are
#     actually exercised -- that's the precise per-file check.
# Plus: orphan tests (deleted class whose name-matched test survives) and stray
# Pest ->only() are hard failures.
#
# Waivers: a "Test-exempt: <path>" trailer in any commit under review.
#
# Usage:  bash scripts/test-sync-check.sh [BASE_REF]
#   BASE_REF defaults to merge-base with origin/backend, else HEAD~1.
# Exit: 0 clean, 1 violations.
# =============================================================================
set -uo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.." || exit 2

BASE="${1:-${TEST_SYNC_BASE:-}}"
[ -z "$BASE" ] && BASE="$(git merge-base HEAD origin/backend 2>/dev/null || git rev-parse HEAD~1 2>/dev/null || echo HEAD)"
echo "test-sync: diffing against $BASE"

is_exempt() {
  case "$1" in
    database/migrations/*|database/seeders/*|database/factories/*) return 0 ;;
    config/*|routes/*|bootstrap/*|resources/views/*|lang/*|public/*) return 0 ;;
  esac; return 1
}

is_critical() {  # invariant-map modules (TESTING.md sec.6): NO waiver, ever
  case "$1" in
    app/Services/Plan/*|app/Models/Bundles.php|app/Helpers/BundleHelper.php) return 0 ;;
    app/Support/OperatingDays.php|*PlanDiscount*|*MovementService*|*PatientLifecycle*) return 0 ;;
  esac; return 1
}

# waivers from commit trailers in the range
ACK="$(git log --format=%B "${BASE}..HEAD" 2>/dev/null | sed -nE 's/^[Tt]est-exempt:[[:space:]]*([^[:space:]]+).*/\1/p' || true)"
is_acked() { printf '%s\n' "$ACK" | grep -qxF "$1" 2>/dev/null; }

CHANGED="$(git diff --name-status "$BASE" 2>/dev/null || true)"
SRC=""; TESTS=""; DEL=""
while IFS=$'\t' read -r st a b; do
  [ -z "${a:-}" ] && continue
  path="$a"; [ "${st:0:1}" = "R" ] && path="${b:-$a}"
  case "$path" in
    *Test.php)               [ "${st:0:1}" = "D" ] || TESTS+="${path}"$'\n' ;;
    app/*.php)
      if [ "${st:0:1}" = "D" ]; then DEL+="${path}"$'\n';
      else is_exempt "$path" || SRC+="${path}"$'\n'; fi ;;
  esac
done <<< "$CHANGED"

FAIL=0
HAS_TEST_CHANGE=0; [ -n "$(printf '%s' "$TESTS" | awk 'NF')" ] && HAS_TEST_CHANGE=1

# --- missing test: logic changed but no test changed (and not waived) ---------
if [ "$HAS_TEST_CHANGE" -eq 0 ]; then
  UNMET=""
  while IFS= read -r f; do
    [ -z "$f" ] && continue
    if is_critical "$f"; then
      # No-waiver rule: a Test-exempt trailer NEVER satisfies an invariant-map module.
      if is_acked "$f"; then UNMET+="  - $f  (CRITICAL -- Test-exempt trailer IGNORED: no-waiver module)"$'\n'
      else UNMET+="  - $f  (CRITICAL -- invariant map, no waiver)"$'\n'; fi
    else
      is_acked "$f" && continue
      UNMET+="  - $f"$'\n'
    fi
  done <<< "$(printf '%s' "$SRC" | awk 'NF' | sort -u)"
  if [ -n "$UNMET" ]; then
    echo "FAIL: backend logic changed but no test changed in this diff:"
    printf '%s' "$UNMET"
    echo "  -> add/extend a behaviour test, or add a 'Test-exempt: <path>' commit trailer with a reason."
    FAIL=1
  fi
fi

# --- orphan: deleted class whose name-matched test still exists ---------------
while IFS= read -r f; do
  [ -z "$f" ] && continue
  cls="$(basename "$f" .php)"
  hit="$(git ls-files "tests/**/${cls}Test.php" 2>/dev/null | head -1 || true)"
  if [ -n "$hit" ]; then echo "FAIL: deleted $f but $hit still exists (orphan -- delete or repoint)"; FAIL=1; fi
done <<< "$(printf '%s' "$DEL" | awk 'NF' | sort -u)"

# --- stray Pest focus --------------------------------------------------------
ONLY="$(grep -rnE '->only\(' tests 2>/dev/null || true)"
if [ -n "$ONLY" ]; then echo "FAIL: stray ->only() (skips the rest of the suite):"; echo "$ONLY" | sed 's/^/  /'; FAIL=1; fi

[ "$FAIL" -eq 0 ] && echo "test-sync: OK"
exit "$FAIL"
