from __future__ import annotations

import argparse
import json
from dataclasses import asdict
from pathlib import Path

from .local_ops import LocalOperations
from .demo import seed_local_demo


def default_root() -> Path:
    return Path(__file__).resolve().parents[2]


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(prog="sifi-local")
    parser.add_argument("action", choices=("init", "status", "backup", "restore", "demo"))
    parser.add_argument("--root", default=str(default_root()))
    parser.add_argument("--backup-path")
    parser.add_argument("--label")
    args = parser.parse_args(argv)
    operations = LocalOperations(args.root)

    if args.action == "init":
        print(operations.initialize().to_json())
    elif args.action == "status":
        print(operations.status().to_json())
    elif args.action == "backup":
        print(json.dumps({"backup": str(operations.backup(label=args.label))}))
    elif args.action == "restore":
        if not args.backup_path:
            parser.error("restore requires --backup-path")
        safety = operations.restore(args.backup_path)
        print(json.dumps({"restored": args.backup_path, "safety_backup": str(safety) if safety else None}))
    else:
        print(json.dumps(asdict(seed_local_demo(args.root)), indent=2, sort_keys=True))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
