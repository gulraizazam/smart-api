#!/usr/bin/env python3
"""Deploy portal.alluraesthetics.pk (the PROD backend) via SSH + git pull.

Unlike the demo backend, portal IS a git checkout on the Hostinger box, so
this script just SSHes in, sets a token-embedded HTTPS remote (once, persists
across future deploys), fast-forwards `origin/<branch>`, and purges the
Laravel bootstrap cache. No file transfer, no SFTP.

Usage:
  python scripts/deploy-portal.py                    # pull origin/backend
  python scripts/deploy-portal.py --branch backend   # explicit branch
  python scripts/deploy-portal.py --dry-run          # show what would pull

Safety:
  - `git pull --ff-only` — aborts if the server has local commits, so a
    diverged prod is surfaced as a failure, not silently overwritten.
  - Fails loud if the working tree has uncommitted modifications that
    would be overwritten by the pull. Nothing is destroyed automatically;
    the operator must reconcile.
  - PHP-syntax-checks the files the pull actually changed before clearing
    cache, so a broken commit is caught before it goes live.

Credentials are read from scripts/deploy-backend.env (git-ignored) — see
scripts/deploy-backend.env.example. Required keys:
  SSH_HOST, SSH_PORT, SSH_USER, SSH_PASS, REMOTE_ROOT
  GH_TOKEN         — GitHub fine-grained PAT with `contents:read` on the
                     repo, so `git fetch` works over HTTPS from the server.

The token is embedded into the server's `origin` URL on first pull; it
stays there for future deploys (rotate GH_TOKEN + re-run this script to
refresh). To scrub it manually, SSH in and:
    git remote set-url origin https://github.com/gulraizazam/smart-api.git

Requires: Python 3.8+ and `pip install paramiko`.
"""
from __future__ import annotations

import argparse
import re
import sys
from pathlib import Path

try:
    import paramiko
except ImportError:
    print("paramiko not installed - run: pip install paramiko", file=sys.stderr)
    sys.exit(2)

REPO = Path(__file__).resolve().parent.parent
ENV_FILE = REPO / "scripts" / "deploy-backend.env"
REPO_HTTPS = "https://github.com/gulraizazam/smart-api.git"


def load_env() -> dict[str, str]:
    if not ENV_FILE.exists():
        sys.exit(
            f"missing {ENV_FILE} - copy scripts/deploy-backend.env.example and fill "
            "in SSH_HOST / SSH_PORT / SSH_USER / SSH_PASS / REMOTE_ROOT / GH_TOKEN"
        )
    env: dict[str, str] = {}
    for raw in ENV_FILE.read_text(encoding="utf-8").splitlines():
        line = raw.strip()
        if not line or line.startswith("#"):
            continue
        k, _, v = line.partition("=")
        env[k.strip()] = v.strip().strip('"').strip("'")
    required = ["SSH_HOST", "SSH_PORT", "SSH_USER", "SSH_PASS", "REMOTE_ROOT", "GH_TOKEN"]
    missing = [k for k in required if not env.get(k)]
    if missing:
        sys.exit(
            f"missing keys in {ENV_FILE}: {', '.join(missing)}\n"
            "GH_TOKEN is a GitHub personal access token with contents:read on "
            "gulraizazam/smart-api (fine-grained recommended)."
        )
    return env


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--branch", default="backend", help="branch to pull (default: backend)")
    ap.add_argument("--dry-run", action="store_true", help="show incoming commits, don't pull")
    args = ap.parse_args()

    env = load_env()
    host, port = env["SSH_HOST"], int(env["SSH_PORT"])
    user, password = env["SSH_USER"], env["SSH_PASS"]
    root = env["REMOTE_ROOT"].rstrip("/")
    token = env["GH_TOKEN"]
    remote_with_token = f"https://{token}@github.com/gulraizazam/smart-api.git"

    def mask(s: str) -> str:
        return re.sub(re.escape(token), "***", s)

    print(f"host    : {host}:{port}")
    print(f"remote  : {root}")
    print(f"branch  : {args.branch}")
    print(f"dry-run : {args.dry_run}\n")

    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(hostname=host, port=port, username=user, password=password,
                   allow_agent=False, look_for_keys=False, timeout=15)

    def sh(cmd: str, allow_fail: bool = False, echo: bool = True) -> tuple[int, str]:
        if echo:
            print(f"$ {mask(cmd)}")
        _, o, e = client.exec_command(cmd, timeout=180)
        out = mask(o.read().decode(errors="replace").rstrip())
        err = mask(e.read().decode(errors="replace").rstrip())
        rc = o.channel.recv_exit_status()
        if out:
            print(out)
        if err:
            print(f"[stderr] {err}")
        if echo:
            print(f"[exit {rc}]\n")
        if rc != 0 and not allow_fail:
            raise RuntimeError(f"remote command failed: {mask(cmd)}")
        return rc, out

    # Pre-flight: confirm it's a git checkout and record current head.
    _, pre_head = sh(f"cd {root} && git rev-parse HEAD")
    _, current_branch = sh(f"cd {root} && git branch --show-current")
    if current_branch != args.branch:
        raise SystemExit(
            f"portal is on branch '{current_branch}', not '{args.branch}'. "
            "Refusing to pull a non-matching branch. Pass --branch or check "
            "the server manually."
        )

    # Refresh the origin URL with a token embedded so unauthenticated HTTPS
    # doesn't prompt for a username on this non-tty session.
    sh(f"cd {root} && git remote set-url origin {remote_with_token}")

    # Fetch + peek incoming
    sh(f"cd {root} && git fetch origin {args.branch} 2>&1")
    _, incoming = sh(
        f"cd {root} && git log --oneline HEAD..origin/{args.branch} 2>&1 | head -30"
    )

    if not incoming.strip():
        print("nothing to pull.")
        client.close()
        return 0

    if args.dry_run:
        print("--dry-run - not pulling.")
        client.close()
        return 0

    # Refuse to pull if there are dirty tracked files (protects live hotfixes).
    _, dirty = sh(f"cd {root} && git status --porcelain 2>&1 | grep -v '^??' || true")
    if dirty.strip():
        client.close()
        raise SystemExit(
            "portal has uncommitted local changes:\n" + dirty +
            "\nRefusing to pull. Reconcile these manually (commit / stash / discard) "
            "and re-run."
        )

    # Pull fast-forward — never a merge, never a rebase.
    sh(f"cd {root} && git pull --ff-only origin {args.branch} 2>&1")

    # PHP-lint the files that changed in this pull.
    _, changed_php = sh(
        f"cd {root} && git diff --name-only {pre_head} HEAD -- '*.php' 2>&1"
    )
    for rel in changed_php.splitlines():
        rel = rel.strip()
        if rel:
            sh(f"php -l {root}/{rel} 2>&1")

    # Ensure R2 driver env vars are set (additive; never clobbers).
    sh(f"grep -q '^R2_DRIVER=' {root}/.env || echo 'R2_DRIVER=local' >> {root}/.env")
    sh(f"grep -q '^R2_INVOICES_DRIVER=' {root}/.env || echo 'R2_INVOICES_DRIVER=local' >> {root}/.env")

    # Ensure storage dirs exist.
    sh(f"mkdir -p {root}/storage/app/public/r2 {root}/storage/app/public/r2_invoices")

    # Purge Laravel bootstrap cache.
    sh(f"cd {root} && rm -f bootstrap/cache/config.php bootstrap/cache/routes-v7.php "
       f"bootstrap/cache/services.php bootstrap/cache/packages.php")

    # Post-state.
    sh(f"cd {root} && git log --oneline -3")

    client.close()
    print("done.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
