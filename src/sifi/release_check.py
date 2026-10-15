from __future__ import annotations

import json
import threading
from dataclasses import asdict, dataclass
from pathlib import Path
from urllib.error import HTTPError
from urllib.request import Request, urlopen

from sifi.local import LocalSIFIPaths
from sifi.local_ops import LocalOperations
from sifi.web.server import create_local_server


@dataclass(frozen=True, slots=True)
class LocalReleaseCheck:
    status: str
    database_integrity: str
    loopback_health: bool
    write_methods_rejected: bool
    required_files_present: bool
    local_only: bool = True
    production_ready_claimed: bool = False

    def to_json(self) -> str:
        return json.dumps(asdict(self), indent=2, sort_keys=True)


def check_local_release(root: str | Path) -> LocalReleaseCheck:
    paths = LocalSIFIPaths.from_root(root)
    status = LocalOperations(paths.root).initialize()
    required = (
        paths.root / "scripts" / "setup-local.ps1",
        paths.root / "scripts" / "demo-local.ps1",
        paths.root / "scripts" / "start-local.ps1",
        paths.root / "scripts" / "backup-local.ps1",
        paths.root / "scripts" / "restore-local.ps1",
        paths.root / "src" / "sifi" / "web" / "static" / "index.html",
        paths.root / "LOCAL_OPERATOR_GUIDE.md",
    )
    files_ok = all(item.is_file() and item.resolve().is_relative_to(paths.root) for item in required)
    server = create_local_server(paths.root, port=0)
    thread = threading.Thread(target=server.serve_forever, daemon=True)
    thread.start()
    base = f"http://127.0.0.1:{server.server_port}"
    health_ok = False
    writes_rejected = False
    try:
        with urlopen(base + "/health", timeout=3) as response:
            payload = json.load(response)
            health_ok = (
                response.status == 200
                and payload.get("status") == "OK"
                and payload.get("local_only") is True
            )
        try:
            urlopen(Request(base + "/api/status", method="POST"), timeout=3)
        except HTTPError as exc:
            try:
                writes_rejected = exc.code == 405
            finally:
                exc.close()
    finally:
        server.shutdown()
        server.server_close()
        thread.join(timeout=3)
    passed = status.integrity == "OK" and health_ok and writes_rejected and files_ok
    return LocalReleaseCheck(
        status="PASS" if passed else "FAIL",
        database_integrity=status.integrity,
        loopback_health=health_ok,
        write_methods_rejected=writes_rejected,
        required_files_present=files_ok,
    )


def main(argv: list[str] | None = None) -> int:
    import argparse

    parser = argparse.ArgumentParser(prog="sifi-release-check")
    parser.add_argument("--root", default=str(Path(__file__).resolve().parents[2]))
    args = parser.parse_args(argv)
    result = check_local_release(args.root)
    print(result.to_json())
    return 0 if result.status == "PASS" else 1


if __name__ == "__main__":
    raise SystemExit(main())
