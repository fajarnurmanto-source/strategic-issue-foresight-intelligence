#!/usr/bin/env python3
"""SIFI local-only SQLite snapshot and verification. Never restores or modifies live DBs.

The backup contains user data and must not be distributed without protection.
"""
from __future__ import annotations

import argparse
import hashlib
import json
import os
from pathlib import Path
import sqlite3
import tempfile
from datetime import datetime, timezone

DB_NAMES = {'laravel': 'laravel.sqlite', 'signals': 'signals.sqlite'}


def digest(path: Path) -> str:
    h = hashlib.sha256()
    with path.open('rb') as f:
        for chunk in iter(lambda: f.read(1024 * 1024), b''):
            h.update(chunk)
    return h.hexdigest()


def check_database(path: Path) -> None:
    with sqlite3.connect(f'file:{path.resolve()}?mode=ro', uri=True) as conn:
        result = conn.execute('PRAGMA quick_check').fetchone()
    if result != ('ok',):
        raise RuntimeError(f'Backup database check failed: {path.name}')


def create_snapshot(sources: dict[str, Path], base: Path) -> Path:
    if set(sources) != set(DB_NAMES):
        raise ValueError('Exact Laravel and signals databases required')
    for name, source in sources.items():
        if source.is_symlink() or not source.is_file():
            raise FileNotFoundError(f'{name} SQLite database missing or symlink')
    old_umask = os.umask(0o077)
    try:
        base.mkdir(mode=0o700, parents=True, exist_ok=True)
        if base.is_symlink():
            raise RuntimeError('Backup folder must not be a symlink')
        os.chmod(base, 0o700)
        work = Path(tempfile.mkdtemp(prefix='.sifi-snapshot-', dir=base))
        metadata: dict[str, object] = {'schema': 'SIFI_LOCAL_SQLITE_BACKUP_V1',
            'created_utc': datetime.now(timezone.utc).isoformat(), 'databases': {}}
        for key, filename in DB_NAMES.items():
            target = work / filename
            with sqlite3.connect(f'file:{sources[key].resolve()}?mode=ro', uri=True, timeout=20) as src:
                with sqlite3.connect(target) as dest:
                    src.backup(dest, pages=128, sleep=0.05)
            check_database(target)
            os.chmod(target, 0o600)
            metadata['databases'][key] = {'filename': filename, 'sha256': digest(target),
                'size_bytes': target.stat().st_size}
        manifest = work / 'manifest.json'
        manifest.write_text(json.dumps(metadata, indent=2, sort_keys=True) + '\n', encoding='utf-8')
        os.chmod(manifest, 0o600)
        final = base / ('snapshot-' + datetime.now(timezone.utc).strftime('%Y%m%dT%H%M%SZ') + '-' + work.name[-6:])
        work.rename(final)
        return final
    finally:
        os.umask(old_umask)


def verify_snapshot(directory: Path) -> bool:
    if directory.is_symlink() or not directory.is_dir():
        raise ValueError('Backup directory missing or symlink')
    data = json.loads((directory / 'manifest.json').read_text(encoding='utf-8'))
    if data.get('schema') != 'SIFI_LOCAL_SQLITE_BACKUP_V1' or set(data.get('databases', {})) != set(DB_NAMES):
        raise ValueError('Invalid snapshot manifest schema')
    for name, expected in DB_NAMES.items():
        record = data['databases'][name]
        if record.get('filename') != expected:
            raise ValueError('Manifest references unexpected filename')
        target = directory / expected
        if target.is_symlink() or not target.is_file():
            raise ValueError('Backup file missing or symlink')
        if digest(target) != record.get('sha256') or target.stat().st_size != record.get('size_bytes'):
            raise ValueError('Backup checksum or length mismatch')
        check_database(target)
    return True


def main() -> None:
    parser = argparse.ArgumentParser(description='Local SIFI SQLite backup; no restore action')
    parser.add_argument('--verify', metavar='SNAPSHOT_FOLDER', help='Verify an existing SIFI snapshot')
    args = parser.parse_args()
    if args.verify:
        print('SIFI_SQLITE_BACKUP_VERIFIED' if verify_snapshot(Path(args.verify)) else 'FAILED')
        return
    home = Path.home() / 'projects'
    sources = {'laravel': home / 'sifi-laravel-vnext/database/database.sqlite',
               'signals': home / 'sifi-aq01-runtime/signals.sqlite'}
    destination = create_snapshot(sources, home / 'sifi-aq01-runtime/manual-backups')
    if not verify_snapshot(destination):
        raise RuntimeError('Post-snapshot verification failed')
    print('SIFI_SQLITE_BACKUP_READY')
    print(f'BACKUP_FOLDER={destination}')
    print('NOTE=Contains sensitive local data; protect snapshots. No automatic restore was performed.')


if __name__ == '__main__':
    main()
