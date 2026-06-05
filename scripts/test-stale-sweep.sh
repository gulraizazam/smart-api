#!/usr/bin/env bash
# =============================================================================
# test-stale-sweep.sh  --  anti-staleness sweep (Laravel backend)
# =============================================================================
#   * STRAY Pest ->only()  -> focus left in code; skips the rest of the suite (FAIL)
#   * Debug leftovers (dd/ dump/ ray/ var_dump) in tests OR app code            (FAIL)
#   * SKIP register: markTestSkipped / markTestIncomplete / ->skip(), listed so
#     they can't pile up silently                                              (INFO)
#
# Exit: 0 clean, 1 violations.
# =============================================================================
set -uo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.." || exit 2
FAIL=0

echo "== Stray Pest ->only() (would skip the rest of the suite) =="
ONLY="$(grep -rnE '->only\(' tests 2>/dev/null || true)"
if [ -n "$ONLY" ]; then echo "$ONLY" | sed 's/^/  /'; FAIL=1; else echo "  none"; fi

echo
echo "== Debug leftovers (dd/dump/ray/var_dump) in app or tests =="
DBG="$(grep -rnE '\b(dd|dump|ray|var_dump)\(' app tests --include='*.php' 2>/dev/null | grep -vE '//.*\b(dd|dump|ray|var_dump)\(' || true)"
if [ -n "$DBG" ]; then echo "$DBG" | sed 's/^/  /'; FAIL=1; else echo "  none"; fi

echo
echo "== Skip register (review periodically; each needs a reason -- see TESTING.md) =="
SKIPS="$(grep -rnE 'markTestSkipped|markTestIncomplete|->skip\(' tests --include='*.php' 2>/dev/null || true)"
if [ -n "$SKIPS" ]; then
  echo "$SKIPS" | sed 's/^/  /'
  echo "  ($(printf '%s\n' "$SKIPS" | wc -l | tr -d ' ') markers)"
else
  echo "  none"
fi

echo
if [ "$FAIL" -ne 0 ]; then echo "test-stale-sweep: FAIL"; exit 1; fi
echo "test-stale-sweep: OK"
