#!/usr/bin/env python3
"""Deploy PHP files to apidemo.smartaesthetics.pk (the DEMO backend) via SSH.

Bypasses the broken SFTP subsystem on this Hostinger box by streaming files
through `ssh exec_command` with base64 chunking. Each file is sha256-verified
on the remote before being moved into place, so a partial upload is never
observed as live.

The DEMO backend is fully isolated from prod: its own subdomain, its own
MySQL DB, its own APP_KEY. Deploys here NEVER touch portal.alluraesthetics.pk.

Usage:
  python scripts/deploy-demo.py                           # files from HEAD
  python scripts/deploy-demo.py --commit HEAD~1           # files from a specific commit
  python scripts/deploy-demo.py --files a.php b.php       # explicit list
  python scripts/deploy-demo.py --dry-run                 # show plan, don't upload

Credentials are read from scripts/deploy-demo-backend.env (git-ignored) — see
scripts/deploy-demo-backend.env.example. Required keys: SSH_HOST, SSH_PORT,
SSH_USER, SSH_PASS, REMOTE_ROOT.

After upload the script also:
  - ensures R2_DRIVER=local and R2_INVOICES_DRIVER=local in the remote .env
    (only when either is missing — never overwrites an existing explicit value)
  - creates storage/app/public/r2 and storage/app/public/r2_invoices if absent
  - purges bootstrap/cache/*.php so the new config/routes take effect

Note on Demo* seeders: includes them (they're the point of the demo stack).

Requires: Python 3.8+ and `pip install paramiko`.
"""
from __future__ import annotations

import argparse
import base64
import hashlib
import shlex
import subprocess
import sys
from pathlib import Path

try:
    import paramiko
except ImportError:
    print("paramiko not installed - run: pip install paramiko", file=sys.stderr)
    sys.exit(2)

REPO = Path(__file__).resolve().parent.parent
ENV_FILE = REPO / "scripts" / "deploy-demo-backend.env"
CHUNK_SIZE = 60_000  # base64 chars per shell command


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
        sys.exit(f"missing keys in {ENV_FILE}: {', '.join(missing)}")
    return env


def files_from_commit(commit: str) -> list[str]:
    out = subprocess.check_output(
        ["git", "show", "--pretty=", "--name-only", commit], cwd=REPO, text=True
    )
    return [line.strip() for line in out.splitlines() if line.strip()]


def is_deployable(rel: str) -> bool:
    # Skip demo scaffolding, tests, docs, deploy scripts themselves.
    skip_prefixes = (
        "tests/", "docs/", ".claude/", ".github/", ".idea/",
        "scripts/deploy-",
    )
    if rel.startswith(skip_prefixes):
        return False
    if rel.endswith((".md", ".env", ".env.example", ".gitignore", ".gitattributes")):
        return False
    return True


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--commit", default="HEAD", help="git ref to source files from")
    ap.add_argument("--files", nargs="+", help="explicit file list (bypasses --commit)")
    ap.add_argument("--dry-run", action="store_true")
    args = ap.parse_args()

    env = load_env()
    host, port = env["SSH_HOST"], int(env["SSH_PORT"])
    user, password = env["SSH_USER"], env["SSH_PASS"]
    root = env["REMOTE_ROOT"].rstrip("/")

    branch = subprocess.check_output(
        ["git", "branch", "--show-current"], cwd=REPO, text=True
    ).strip()

    rels = args.files or [f for f in files_from_commit(args.commit) if is_deployable(f)]
    rels = [r for r in rels if (REPO / r).exists()]

    print(f"host    : {host}:{port}")
    print(f"remote  : {root}")
    print(f"branch  : {branch}")
    print(f"commit  : {args.commit}")
    print(f"files   : {len(rels)}")
    for r in rels:
        print(f"  {r}")

    if args.dry_run:
        print("--dry-run - not uploading.")
        return 0
    if not rels:
        print("nothing to upload.")
        return 0

    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    client.connect(hostname=host, port=port, username=user, password=password,
                   allow_agent=False, look_for_keys=False, timeout=15)

    def sh(cmd: str, allow_fail: bool = False, echo: bool = False) -> tuple[int, str]:
        if echo:
            print(f"$ {cmd}")
        _, o, e = client.exec_command(cmd, timeout=120)
        out = o.read().decode(errors="replace").rstrip()
        err = e.read().decode(errors="replace").rstrip()
        rc = o.channel.recv_exit_status()
        if echo and out:
            print(out)
        if err:
            print(f"[stderr] {err}")
        if rc != 0 and not allow_fail:
            raise RuntimeError(f"remote command failed: {cmd!r}")
        return rc, out

    def upload(local: Path, rel: str) -> None:
        remote = f"{root}/{rel}"
        parent = "/".join(remote.split("/")[:-1])
        data = local.read_bytes()
        local_sha = hashlib.sha256(data).hexdigest()
        b64 = base64.b64encode(data).decode("ascii")
        print(f"  {rel}  ({len(data)} bytes)")
        sh(f"mkdir -p {shlex.quote(parent)}")
        tmp = f"{remote}.newdeploy"
        sh(f": > {shlex.quote(tmp)}.b64")
        for i in range(0, len(b64), CHUNK_SIZE):
            chunk = b64[i:i + CHUNK_SIZE]
            sh(f"printf %s {shlex.quote(chunk)} >> {shlex.quote(tmp)}.b64")
        sh(f"base64 -d {shlex.quote(tmp)}.b64 > {shlex.quote(tmp)} && rm {shlex.quote(tmp)}.b64")
        _, remote_sha = sh(f"sha256sum {shlex.quote(tmp)} | awk '{{print $1}}'")
        if remote_sha != local_sha:
            sh(f"rm -f {shlex.quote(tmp)}", allow_fail=True)
            raise RuntimeError(f"sha mismatch on {rel}: local={local_sha}, remote={remote_sha}")
        sh(f"mv {shlex.quote(tmp)} {shlex.quote(remote)}")

    print("\nuploading:")
    for rel in rels:
        upload(REPO / rel, rel)

    # If a config/filesystems.php upload just happened, make sure the R2
    # driver env vars exist and the storage roots are present. Additive
    # only — never clobbers an existing explicit value.
    touched_storage = any(
        r in ("config/filesystems.php", "app/Providers/AppServiceProvider.php")
        for r in rels
    )
    if touched_storage:
        print("\nensuring R2 env vars (additive only):")
        sh(f"grep -q '^R2_DRIVER=' {root}/.env || echo 'R2_DRIVER=local' >> {root}/.env",
           echo=True)
        sh(f"grep -q '^R2_INVOICES_DRIVER=' {root}/.env || echo 'R2_INVOICES_DRIVER=local' >> {root}/.env",
           echo=True)
        sh(f"grep -E '^R2_' {root}/.env", echo=True)

        print("ensuring storage dirs:")
        sh(f"mkdir -p {root}/storage/app/public/r2 {root}/storage/app/public/r2_invoices",
           echo=True)

    # PHP-lint every uploaded .php file (fail-loud on syntax errors before
    # they hit the request loop).
    php_files = [r for r in rels if r.endswith(".php")]
    if php_files:
        print("\nphp-lint:")
        for r in php_files:
            sh(f"php -l {root}/{r} 2>&1", echo=True)

    print("\npurging bootstrap cache:")
    sh(f"cd {root} && rm -f bootstrap/cache/config.php bootstrap/cache/routes-v7.php "
       f"bootstrap/cache/services.php bootstrap/cache/packages.php")
    sh(f"ls {root}/bootstrap/cache/", echo=True)

    client.close()
    print("\ndone.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
