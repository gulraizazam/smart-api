#!/usr/bin/env python3
"""Deploy PHP files to apidemo.smartaesthetics.pk (the DEMO backend) via SFTP.

Sibling of scripts/deploy-backend.py — same shape, different target:
  portal.alluraesthetics.pk  →  /home/u941750079/domains/alluraesthetics.pk/public_html/portal
  apidemo.smartaesthetics.pk →  /home/u941750079/domains/smartaesthetics.pk/public_html/api_demo

The demo backend is fully isolated from prod: its own subdomain, its own MySQL
DB (u941750079_demo_db), its own APP_KEY. Deploys here NEVER touch prod. Use
this when you want to ship a demo-only change to apidemo without affecting
portal.alluraesthetics.pk.

Usage:
  python scripts/deploy-demo-backend.py                        # files from HEAD
  python scripts/deploy-demo-backend.py --commit HEAD~1        # files from a specific commit
  python scripts/deploy-demo-backend.py --files a.php b.php    # explicit list
  python scripts/deploy-demo-backend.py --dry-run              # show plan, don't upload

Credentials are read from scripts/deploy-demo-backend.env (git-ignored) — see
scripts/deploy-demo-backend.env.example. Required keys: SSH_HOST, SSH_PORT, SSH_USER,
SSH_PASS, REMOTE_ROOT.

Requires: Python 3.8+ and `pip install paramiko`.

Note on Demo* seeders: unlike deploy-backend.py (which SKIPS them because demo
data doesn't belong in prod), this script INCLUDES them — they're the point of
the demo stack.
"""
from __future__ import annotations

import argparse
import posixpath
import subprocess
import sys
import time
from pathlib import Path

try:
    import paramiko
except ImportError:
    print("paramiko not installed - run: pip install paramiko", file=sys.stderr)
    sys.exit(2)

REPO = Path(__file__).resolve().parent.parent
ENV_FILE = REPO / "scripts" / "deploy-demo-backend.env"


def load_env() -> dict[str, str]:
    if not ENV_FILE.exists():
        sys.exit(
            f"missing {ENV_FILE} - copy scripts/deploy-demo-backend.env.example and fill "
            "in SSH_HOST / SSH_PORT / SSH_USER / SSH_PASS / REMOTE_ROOT"
        )
    env: dict[str, str] = {}
    for raw in ENV_FILE.read_text(encoding="utf-8").splitlines():
        line = raw.strip()
        if not line or line.startswith("#"):
            continue
        k, _, v = line.partition("=")
        env[k.strip()] = v.strip().strip('"').strip("'")
    required = ["SSH_HOST", "SSH_PORT", "SSH_USER", "SSH_PASS", "REMOTE_ROOT"]
    missing = [k for k in required if not env.get(k)]
    if missing:
        sys.exit(f"{ENV_FILE} is missing keys: {', '.join(missing)}")
    return env


def files_from_commit(rev: str) -> list[str]:
    out = subprocess.check_output(
        ["git", "diff-tree", "--no-commit-id", "--name-only", "-r", rev],
        cwd=REPO,
        text=True,
    ).strip()
    return [f for f in out.splitlines() if f]


def current_branch() -> str:
    return subprocess.check_output(
        ["git", "rev-parse", "--abbrev-ref", "HEAD"], cwd=REPO, text=True
    ).strip()


def open_conn(env: dict[str, str]) -> tuple[paramiko.SSHClient, paramiko.SFTPClient]:
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(
        env["SSH_HOST"],
        port=int(env["SSH_PORT"]),
        username=env["SSH_USER"],
        password=env["SSH_PASS"],
        allow_agent=False,
        look_for_keys=False,
        timeout=60,
        banner_timeout=60,
        auth_timeout=60,
    )
    sftp = client.open_sftp()
    return client, sftp


def run(client: paramiko.SSHClient, cmd: str, timeout: int = 300) -> tuple[int, str, str]:
    _, stdout, stderr = client.exec_command(cmd, timeout=timeout)
    out = stdout.read().decode(errors="replace")
    err = stderr.read().decode(errors="replace")
    return stdout.channel.recv_exit_status(), out, err


def main() -> int:
    p = argparse.ArgumentParser()
    grp = p.add_mutually_exclusive_group()
    grp.add_argument("--commit", default="HEAD", help="Git rev whose changed files to deploy (default HEAD)")
    grp.add_argument("--files", nargs="+", help="Explicit list of repo-relative file paths")
    p.add_argument("--dry-run", action="store_true")
    p.add_argument("--allow-any-branch", action="store_true",
                   help="Skip the on-demo-branch gate (use for hot-fixing from a topic branch)")
    args = p.parse_args()

    # Gate: refuse to deploy from anything but demo unless overridden.
    br = current_branch()
    if br != "demo" and not args.allow_any_branch:
        sys.exit(
            f"REFUSING: on branch '{br}', not 'demo'. Demo deploys should come from "
            "the 'demo' branch so what's live matches what's in origin/demo. "
            "Pass --allow-any-branch to override."
        )

    env = load_env()
    remote_root = env["REMOTE_ROOT"].rstrip("/")

    files = args.files or files_from_commit(args.commit)
    # PHP only; skip test / CI / local-deploy files.
    # NOTE: unlike deploy-backend.py, we DO include database/seeders/Demo* here —
    # they're the whole point of the demo stack.
    SKIP_PREFIX = ("tests/", "scripts/", ".github/")
    files = [
        f for f in files
        if f.endswith((".php", ".blade.php"))
        and not any(f.startswith(p) for p in SKIP_PREFIX)
    ]
    if not files:
        print("no deployable PHP files in that changeset - nothing to do.")
        return 0

    print(f"host    : {env['SSH_HOST']}:{env['SSH_PORT']}")
    print(f"remote  : {remote_root}")
    print(f"branch  : {br}")
    print(f"files   : {len(files)}")
    for f in files:
        local = REPO / f
        if not local.exists():
            sys.exit(f"missing local file: {local}")
        print(f"  {f}")

    if args.dry_run:
        print("--dry-run - not uploading.")
        return 0

    print("connecting…")
    client, sftp = open_conn(env)
    try:
        stamp = int(time.time())
        for f in files:
            local = REPO / f
            remote = posixpath.join(remote_root, f.replace("\\", "/"))
            backup = f"{remote}.bak.{stamp}"
            try:
                sftp.stat(remote)
                run(client, f"cp -p '{remote}' '{backup}'")
                print(f"backup  {backup}")
            except FileNotFoundError:
                print(f"new     {remote} (no backup)")
            sftp.put(str(local), remote)
            size = sftp.stat(remote).st_size
            print(f"upload  {remote}  ({size} bytes)")

        print("php -l on uploaded files…")
        for f in files:
            remote = posixpath.join(remote_root, f.replace("\\", "/"))
            rc, out, err = run(client, f"php -l '{remote}'")
            if rc != 0:
                print(f"SYNTAX ERROR: {remote}\n{out}{err}", file=sys.stderr)
                print("Rolling forward is unsafe - restore .bak manually.", file=sys.stderr)
                return 3
            print(f"  ok    {f}")

        print("clearing Laravel bootstrap/cache/*.php (if any)…")
        rc, out, err = run(
            client,
            f"cd '{remote_root}' && find bootstrap/cache -maxdepth 1 -name '*.php' -type f -delete -print 2>&1 | wc -l",
        )
        print(f"  cleared {out.strip()} cache file(s)")
    finally:
        sftp.close()
        client.close()

    print("done.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
