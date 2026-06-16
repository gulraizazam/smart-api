#!/usr/bin/env bash
#
# deploy-api.sh - the SANCTIONED, gated way to deploy the Laravel backend to
# api.cutera.pk. FAIL-CLOSED: prod can only ever run code that is EXACTLY
# origin/backend, passed the full test suite, passed the crm2 coexistence
# check, and is >= prod (no schema drift, local ahead). Deploy mechanics are
# the documented manual flow: git push local->server (the box can't pull from
# Bitbucket), then migrate + clear caches with php8.4. See
# project_deploy_pipelines / project_local_vs_production_db.
#
# Prerequisites:
#   - Local coexistence mirror up (Herd backend.test + crm2.test) for Gate 4.
#   - Read SSH access to the box for Gate 5 (parity) - pre-authorized.
#
# Usage:
#   bash scripts/deploy-api.sh --check   # run ALL gates, do NOT deploy
#   bash scripts/deploy-api.sh           # gates, then deploy (asks first)
#
# Overridable (defaults = known prod target):
#   SSH_KEY SSH_PORT SSH_HOST API_REMOTE_DIR PHP84 DEPLOY_BRANCH API_URL CRM2_URL
#
set -euo pipefail

BACKEND="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$BACKEND"

DEPLOY_BRANCH="${DEPLOY_BRANCH:-backend}"
SSH_KEY="${SSH_KEY:-$HOME/.ssh/cuteraesthetics_hostinger}"
SSH_PORT="${SSH_PORT:-65002}"
SSH_HOST="${SSH_HOST:-u572390775@156.67.66.146}"
API_REMOTE_DIR="${API_REMOTE_DIR:-/home/u572390775/domains/api.cutera.pk/crm}"
PHP84="${PHP84:-/opt/alt/php84/usr/bin/php}"
API_URL="${API_URL:-https://api.cutera.pk}"
CRM2_URL="${CRM2_URL:-https://crm2.cutera.pk}"
LOCAL_PHP="${LOCAL_PHP:-$(command -v php || echo php)}"  # may contain spaces (Herd) - always quote

CHECK_ONLY=0; [ "${1:-}" = "--check" ] && CHECK_ONLY=1

say()  { printf '\n>> %s\n' "$*"; }
warn() { printf '   WARN: %s\n' "$*" >&2; }
die()  { printf '\nREFUSING: %s\n' "$*" >&2; exit 1; }
ssh_prod() { ssh -i "$SSH_KEY" -p "$SSH_PORT" "$SSH_HOST" "$@"; }
http() { curl -skS -o /dev/null -w '%{http_code}' -H 'Accept: application/json' "$1" 2>/dev/null || echo 000; }

# ---- Gate 1: on the backend release branch, clean tree ----------------------
say "Gate 1/5: on '$DEPLOY_BRANCH' with a clean tree"
branch="$(git rev-parse --abbrev-ref HEAD)"
[ "$branch" = "$DEPLOY_BRANCH" ] \
  || die "on '$branch', not '$DEPLOY_BRANCH'. Deploy only from the backend release branch (merge shahid -> backend first)."
git diff --quiet && git diff --cached --quiet \
  || die "working tree has uncommitted changes - commit or stash first."
HEAD_SHA="$(git rev-parse HEAD)"
HEAD_SHORT="$(git rev-parse --short HEAD)"
echo "   HEAD = $HEAD_SHORT"

# ---- Gate 2: local == origin/backend (no laptop-only code) ------------------
say "Gate 2/5: local '$DEPLOY_BRANCH' is in sync with origin/$DEPLOY_BRANCH"
git fetch origin "$DEPLOY_BRANCH" --quiet
[ "$HEAD_SHA" = "$(git rev-parse "origin/$DEPLOY_BRANCH")" ] \
  || die "local $DEPLOY_BRANCH != origin/$DEPLOY_BRANCH. Push/pull so you deploy EXACTLY origin -
         prod must never run laptop-only code (this is what caused the 2026-06-03 drift)."

# ---- Gate 3: full backend test suite ----------------------------------------
say "Gate 3/5: full backend test suite (pest)"
"$LOCAL_PHP" -d memory_limit=-1 vendor/bin/pest \
  || die "backend test suite FAILED - fix before deploying."

# ---- Gate 4: crm2 coexistence (local shared-DB mirror) ----------------------
say "Gate 4/5: coexistence-check.sh (this code must not break crm2)"
"$LOCAL_PHP" artisan config:clear >/dev/null 2>&1 || true
bash scripts/coexistence-check.sh \
  || die "coexistence-check FAILED - this code would break crm2 on the shared DB. Refusing."

# ---- Gate 5: parity (local >= prod, no drift, crm2 200) ---------------------
say "Gate 5/5: parity-check.sh (local >= prod, no schema drift, crm2 healthy)"
PARITY_PROD_SSH="-p $SSH_PORT -i $SSH_KEY $SSH_HOST" \
PARITY_PROD_ARTISAN="cd $API_REMOTE_DIR && $PHP84 artisan" \
  bash scripts/parity-check.sh \
  || die "parity-check FAILED - prod has schema drift or is AHEAD of local, or crm2 is down. Refusing."

if [ "$CHECK_ONLY" = 1 ]; then
  say "ALL GATES PASSED (--check). Not deploying."
  exit 0
fi

# ---- Deploy -----------------------------------------------------------------
if [ "${DEPLOY_YES:-}" = 1 ]; then
  say "All gates passed. DEPLOY_YES=1 -> deploying $HEAD_SHORT non-interactively."
else
  printf '\nAll gates passed. Deploy %s to %s:%s ? [y/N] ' "$HEAD_SHORT" "$SSH_HOST" "$API_REMOTE_DIR"
  # Read from the TTY, not stdin: an earlier ssh (parity-check) drains piped stdin.
  read -r ans </dev/tty 2>/dev/null || ans=""
  [ "$ans" = y ] || [ "$ans" = Y ] || die "aborted by operator."
fi

# Capture the live SHA BEFORE pushing, for rollback.
PREV_SHA="$(ssh_prod "cd '$API_REMOTE_DIR' && git rev-parse HEAD" 2>/dev/null || echo unknown)"
say "Server currently at: $PREV_SHA (rollback anchor)"

say "Pushing $DEPLOY_BRANCH -> server working tree (receive.denyCurrentBranch=updateInstead)"
GIT_SSH_COMMAND="ssh -i $SSH_KEY" \
  git push "ssh://$SSH_HOST:$SSH_PORT$API_REMOTE_DIR" "$DEPLOY_BRANCH"

# composer install only when composer.lock actually changed in this deploy.
if [ "$PREV_SHA" != unknown ] && git cat-file -e "${PREV_SHA}^{commit}" 2>/dev/null; then
  if git diff --quiet "$PREV_SHA" "$HEAD_SHA" -- composer.lock; then
    echo "   composer.lock unchanged - skipping composer install"
  else
    say "composer.lock changed -> composer install on the server"
    ssh_prod "cd '$API_REMOTE_DIR' && composer install --no-dev --optimize-autoloader --no-interaction" \
      || warn "composer install failed - run it manually on the server (php8.4) before trusting this deploy."
  fi
else
  warn "previous server SHA unknown/not local - if deps changed, run composer install on the server manually."
fi

# migrate, then REBUILD the prod caches (config/route/view/event) rather than
# just clearing them - the speed-up. Safe because env() lives only in config/*
# (pinned by ConfigCacheSafetyTest). optimize:clear first wipes the OLD code's
# stale caches; optimize rebuilds for the new code. NOTE: with config cached, a
# later prod .env edit needs a re-run of `optimize` to take effect.
say "migrate --force + rebuild prod caches (php8.4: optimize = config/route/view/event)"
ssh_prod "cd '$API_REMOTE_DIR' && \
  $PHP84 artisan migrate --force && \
  $PHP84 artisan optimize:clear && \
  $PHP84 artisan optimize && \
  $PHP84 artisan permission:cache-reset"

say "Stamping deploy-version.json on the server (read by GET /api/version)"
ssh_prod "cd '$API_REMOTE_DIR' && printf '{\"commit\":\"%s\",\"builtAt\":\"%s\"}\n' \
  '$HEAD_SHORT' '$(date -u +%Y-%m-%dT%H:%M:%SZ)' > storage/app/deploy-version.json"

# ---- Verify -----------------------------------------------------------------
say "Verify"
u="$(http "$API_URL/api/user")"
echo "   $API_URL/api/user    -> $u (want 401 for JSON = app up, auth enforced)"
[ "$u" = 401 ] || warn "api/user not 401 - investigate."
v="$(curl -skS "$API_URL/api/version" 2>/dev/null | sed -n 's/.*"commit"[^"]*"\([^"]*\)".*/\1/p')"
echo "   $API_URL/api/version -> commit $v"
[ "$v" = "$HEAD_SHORT" ] && say "  /api/version matches deploy ($v)" || warn "/api/version=$v != deployed $HEAD_SHORT"
# crm2 is intentionally Basic-Auth locked for maintenance (same creds as the crm3
# gate); 200 (authed) or 401 (gate up) both mean it's alive — only an unreachable
# box (000/5xx) is a real problem. Authenticate when the gate creds are present.
CRM2_GATE_AUTH="${CRM2_GATE_AUTH:-${CRM3_GATE_AUTH:-}}"
if [ -n "$CRM2_GATE_AUTH" ]; then
  c2="$(curl -skS -u "$CRM2_GATE_AUTH" -o /dev/null -w '%{http_code}' "$CRM2_URL/login" 2>/dev/null)"
else
  c2="$(curl -skS -o /dev/null -w '%{http_code}' "$CRM2_URL/login" 2>/dev/null)"
fi
echo "   $CRM2_URL/login -> ${c2:-000} (200 authed / 401 maintenance lock = crm2 OK)"
case "${c2:-000}" in 200|401) ;; *) warn "crm2 ${c2:-000} after deploy - investigate immediately." ;; esac

say "DONE. Deployed $HEAD_SHORT to api."
say "Rollback: ssh in, 'cd $API_REMOTE_DIR && git reset --hard $PREV_SHA', then migrate/cache as needed."
