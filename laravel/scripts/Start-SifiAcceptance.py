#!/usr/bin/env python3
"""Run a pinned, isolated SIFI browser UAT with persistent identity.

This NEVER creates or migrates a database, resets accounts, bypasses verification,
uses live runtime data, or changes the active SIFI service. Python 3.10+ on WSL.
"""
import argparse
import base64
import os
from pathlib import Path
import secrets
import signal
import socket
import sqlite3
import stat
import subprocess
import sys


def fail(code: str) -> None:
    raise SystemExit("BLOCKED_" + code)


def main() -> None:
    parser = argparse.ArgumentParser(description="SIFI isolated, persistent UAT server")
    parser.add_argument("--app", default="/home/fajar/sifi-r7-acceptance/rc-abb58f6-boot-01")
    parser.add_argument("--db", default="/home/fajar/sifi-r7-acceptance/sifi-r7-http-87a1hqn2/acceptance.sqlite")
    parser.add_argument("--state-dir", default="/home/fajar/.local/state/sifi-r7-acceptance")
    parser.add_argument("--port", type=int, default=18821)
    args = parser.parse_args()

    app = Path(args.app).resolve(strict=True)
    db = Path(args.db).resolve(strict=True)
    state_dir = Path(args.state_dir).expanduser()

    acceptance_root = Path("/home/fajar/sifi-r7-acceptance").resolve(strict=True)
    live_root = Path("/home/fajar/projects/sifi-laravel-vnext").resolve(strict=True)

    if not app.is_relative_to(acceptance_root) or app == live_root or app.is_relative_to(live_root):
        fail("ACCEPTANCE_SOURCE_NOT_ISOLATED")
    if not db.is_relative_to(acceptance_root) or db == app / "database/database.sqlite":
        fail("DATABASE_NOT_ISOLATED")
    if app.is_symlink() or db.is_symlink():
        fail("SYMLINK_SOURCE_OR_DATABASE")
    if not all((app / p).is_file() for p in ("artisan", "vendor/autoload.php", "public/build/manifest.json")):
        fail("ACCEPTANCE_DEPENDENCY_OR_ASSET_MISSING")
    if (app / ".env").exists() or (app / "bootstrap/cache/config.php").exists():
        fail("ACCEPTANCE_ENV_OR_CACHED_CONFIG_PRESENT")
    if not 1024 <= args.port <= 65535:
        fail("UNSAFE_PORT")

    with sqlite3.connect("file:" + str(db) + "?mode=ro", uri=True) as connection:
        tables = {row[0] for row in connection.execute("select name from sqlite_master where type='table'")}
        if "users" not in tables or "migrations" not in tables:
            fail("DATABASE_NOT_MIGRATED")
        if connection.execute("select count(*) from users").fetchone()[0] < 1:
            fail("DATABASE_HAS_NO_USERS_NO_AUTOMATIC_RESET")

    with socket.socket() as sock:
        if sock.connect_ex(("127.0.0.1", args.port)) == 0:
            fail("PORT_IN_USE_STOP_OLD_UAT_SERVER_ONCE")

    # Stable signing/cookie key across restarts. Never put this file in Git.
    state_dir.mkdir(mode=0o700, parents=True, exist_ok=True)
    if state_dir.is_symlink() or stat.S_IMODE(state_dir.stat().st_mode) & 0o077:
        fail("INSECURE_STATE_DIRECTORY")
    # Pin one database identity for the lifetime of this UAT state directory.
    db_marker = state_dir / "database_path"
    if not db_marker.exists():
        try:
            fd = os.open(str(db_marker), os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600)
        except FileExistsError:
            pass
        else:
            with os.fdopen(fd, "w", encoding="utf-8") as output:
                output.write(str(db) + "\n")
    if db_marker.is_symlink() or stat.S_IMODE(db_marker.stat().st_mode) & 0o077:
        fail("INSECURE_DATABASE_IDENTITY_FILE")
    if db_marker.read_text(encoding="utf-8").strip() != str(db):
        fail("DATABASE_IDENTITY_CHANGED_REVIEW_REQUIRED")

    key_file = state_dir / "app_key"
    if not key_file.exists():
        try:
            fd = os.open(str(key_file), os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600)
        except FileExistsError:
            pass
        else:
            with os.fdopen(fd, "w", encoding="ascii") as output:
                output.write("base64:" + base64.b64encode(secrets.token_bytes(32)).decode("ascii") + "\n")
            print("ACCEPTANCE_KEY=GENERATED_ONCE", flush=True)
    if key_file.is_symlink() or stat.S_IMODE(key_file.stat().st_mode) & 0o077:
        fail("INSECURE_KEY_FILE")
    key = key_file.read_text(encoding="ascii").strip()
    if not key.startswith("base64:") or len(base64.b64decode(key[7:], validate=True)) != 32:
        fail("INVALID_PERSISTENT_APP_KEY")

    # All security-critical config is pinned; discard inherited DB URLs and env.
    env = os.environ.copy()
    for setting in ("DATABASE_URL", "DB_URL", "DB_HOST", "DB_USERNAME", "DB_PASSWORD",
                    "DB_CONNECTION", "DB_DATABASE", "APP_KEY", "APP_URL", "SESSION_DOMAIN"):
        env.pop(setting, None)
    env.update({
        "APP_NAME": "SIFI_R7_ACCEPTANCE",
        "APP_ENV": "testing",
        "APP_DEBUG": "false",
        "APP_KEY": key,
        "APP_URL": "http://localhost:" + str(args.port),
        "DB_CONNECTION": "sqlite",
        "DB_DATABASE": str(db),
        "SESSION_DRIVER": "file",
        "SESSION_COOKIE": "sifi_r7_acceptance_session",
        "CACHE_STORE": "array",
        "QUEUE_CONNECTION": "sync",
        "FILESYSTEM_DISK": "local",
        "MAIL_MAILER": "log",
        "MAIL_LOG_CHANNEL": "single",
        "LOG_CHANNEL": "single",
        "SESSION_SECURE_COOKIE": "false",
    })

    os.umask(0o077)
    print("SIFI_UAT_PERSISTENT_CONFIG=PASS", flush=True)
    print("ACCEPTANCE_DATABASE_REUSED=YES", flush=True)
    print("ACCOUNTS_NOT_RECREATED=YES", flush=True)
    print("APP_KEY_REUSED=YES", flush=True)
    print("EMAIL_LOG_LOCATION=" + str(app / "storage/logs/laravel.log"), flush=True)
    print("UAT_URL=http://localhost:" + str(args.port), flush=True)
    print("Press Ctrl+C once to stop this UAT server.", flush=True)

    try:
        process = subprocess.Popen(
            ["php", "artisan", "serve", "--host=127.0.0.1", "--port=" + str(args.port)],
            cwd=str(app), env=env, start_new_session=True
        )
        process.wait()
    except KeyboardInterrupt:
        print("STOP_REQUESTED", flush=True)
    finally:
        if "process" in locals() and process.poll() is None:
            os.killpg(process.pid, signal.SIGTERM)
            try:
                process.wait(timeout=10)
            except subprocess.TimeoutExpired:
                os.killpg(process.pid, signal.SIGKILL)
        print("SIFI_UAT_SERVER_STOPPED=YES", flush=True)


if __name__ == "__main__":
    main()
