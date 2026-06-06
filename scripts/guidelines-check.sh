#!/usr/bin/env bash
# =============================================================================
# guidelines-check.sh  --  machine-checkable GUIDELINES.md rules for backend PHP
# =============================================================================
# Backend CI mirror of the frontend repo's .claude/hooks/guidelines-guard.sh.
# The frontend hook also scans backend files, but ONLY when run from a frontend
# session; the backend's own CI can't reach the frontend repo, so the PHP rules
# are duplicated here on purpose (separate repo / separate CI checkout -- logged
# per the "must duplicate -> say why" rule). Keep the two rule sets in sync.
#
#   --check [BASE]   scan files changed since BASE (CI: the merge-base), added
#                    lines only, and BLOCK on a violation. No BASE -> working tree.
#
# Block-by-default, waive-on-the-record:
#   * Commit trailer  "Guidelines-exempt: <rule-id>"  (or "<file>::<rule-id>")
#   * Local file      .claude/guidelines-ack           (same format, if present)
# Fail-open on any git/tool error; only the [M] PHP rules live here.
# =============================================================================
set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." 2>/dev/null && pwd || true)"
cd "$ROOT" 2>/dev/null || exit 0
git rev-parse --git-dir >/dev/null 2>&1 || exit 0

BASE_REF=""
[[ "${1:-}" == "--check" ]] && BASE_REF="${2:-}"
# allow bare "BASE" too (CI calls: guidelines-check.sh "$BASE")
[[ -z "$BASE_REF" && -n "${1:-}" && "${1:-}" != "--check" ]] && BASE_REF="$1"
DIFF_BASE="HEAD"; [ -n "$BASE_REF" ] && DIFF_BASE="$BASE_REF"

# --- waivers -----------------------------------------------------------------
ACK_TOKENS=""
[ -f ".claude/guidelines-ack" ] && ACK_TOKENS="$(sed -e 's/[[:space:]]*--.*$//' -e 's/#.*$//' .claude/guidelines-ack 2>/dev/null | awk 'NF' || true)"
if [ -n "$BASE_REF" ]; then
  _tr="$(git log --format=%B "${BASE_REF}..HEAD" 2>/dev/null | sed -nE 's/^[Gg]uidelines-exempt:[[:space:]]*([^[:space:]]+).*/\1/p' || true)"
  [ -n "$_tr" ] && ACK_TOKENS="${ACK_TOKENS}"$'\n'"${_tr}"
fi
is_acked() { printf '%s\n' "$ACK_TOKENS" | grep -qxF "$1" 2>/dev/null; }

# --- added lines of a file ---------------------------------------------------
added_lines() {
  local rel="$1"
  if git ls-files --error-unmatch "$rel" >/dev/null 2>&1; then
    git diff -U0 "$DIFF_BASE" -- "$rel" 2>/dev/null | grep -E '^\+' | grep -vE '^\+\+\+' | sed 's/^+//'
  else
    cat "$rel" 2>/dev/null
  fi
}

FINDINGS=""
add_finding() { # file rule-id message sample
  is_acked "$2" && return 0
  is_acked "${1}::${2}" && return 0
  local sample; sample="$(printf '%s' "$4" | sed -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//' | cut -c1-100)"
  FINDINGS+="${1}"$'\t'"${2}"$'\t'"${3}"$'\t'"${sample}"$'\n'
}
LINES=""
flag() { # file rule-id regex message   ( -e: pattern may begin with '-' )
  local hit; hit="$(printf '%s\n' "$LINES" | grep -nE -e "$3" 2>/dev/null | head -1 || true)"
  [ -n "$hit" ] && add_finding "$1" "$2" "$4" "${hit#*:}"
}

scan() { # repo-relative php file
  local f="$1"
  case "$f" in *.php) ;; *) return 0 ;; esac
  [ -f "$f" ] || return 0
  LINES="$(added_lines "$f")"; [ -z "$LINES" ] && return 0
  case "$f" in
    database/migrations/*.php)
      flag "$f" "money-float" '->[[:space:]]*(float|double)\(' \
        "Don't use float/double for money -- use ->decimal() (or integer minor units). If not money, waive (GUIDELINES C4)."
      flag "$f" "migration-destructive" '->[[:space:]]*(dropColumn|renameColumn|dropForeign|dropUnique|dropIndex|dropPrimary|dropMorphs|dropConstrainedForeignId)\(|Schema::[[:space:]]*drop(IfExists)?\(' \
        "Non-additive migration (drop/rename) -- can break crm2's reads on the shared DB. Additive-only; run coexistence-check.sh; waive ONLY if confirmed crm2-safe (GUIDELINES C4)." ;;
    app/Http/Requests/*.php|app/Http/Controllers/*.php)
      flag "$f" "bare-exists" "['\"]exists:" \
        "Cross-tenant FK: use Rule::exists()->where('account_id', ...), not a bare exists: rule (GUIDELINES Part B: API)."
      flag "$f" "raw-sql-interp" 'DB::raw\([^)]*\$' \
        "SQL-injection risk: parameterize; don't interpolate a variable into DB::raw (GUIDELINES Part B: DB)." ;;
  esac
}

# --- collect changed files ---------------------------------------------------
if [ -n "$BASE_REF" ]; then
  FILES="$(git diff --name-only "$BASE_REF" 2>/dev/null || true)"
else
  FILES="$(git status --porcelain=v1 -uall 2>/dev/null | sed -E 's/^...//; s/^.* -> //; s/^"//; s/"$//')"
fi
while IFS= read -r f; do [ -n "$f" ] && scan "$f"; done <<< "$FILES"

# --- emit --------------------------------------------------------------------
if [ -z "$FINDINGS" ]; then
  echo "guidelines: OK -- no machine-checkable PHP violations in changed code."
  exit 0
fi
echo "GUIDELINES GATE (backend): changed PHP trips machine-checkable rules in GUIDELINES.md."
echo
while IFS=$'\t' read -r f rid msg sample; do
  [ -z "$f" ] && continue
  echo "  - ${f}  [${rid}]"
  echo "      ${msg}"
  [ -n "$sample" ] && echo "      > ${sample}"
done <<< "$(printf '%s' "$FINDINGS" | sort -u)"
echo
echo "Fix the code, OR waive on the record:"
echo "    commit trailer:  Guidelines-exempt: <rule-id>   (or <file>::<rule-id>)"
echo "    local file:      .claude/guidelines-ack          (same format)"
echo "Charter: GUIDELINES.md (in the frontend repo)."
exit 1
