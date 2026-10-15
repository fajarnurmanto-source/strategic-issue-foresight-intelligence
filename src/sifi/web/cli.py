from __future__ import annotations

import argparse
import threading
import webbrowser
from pathlib import Path

from sifi.local_ops import LocalOperations

from .server import create_local_server


def default_root() -> Path:
    return Path(__file__).resolve().parents[3]


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(prog="sifi-web")
    parser.add_argument("--root", default=str(default_root()))
    parser.add_argument("--port", type=int, default=8765)
    parser.add_argument("--open-browser", action="store_true")
    args = parser.parse_args(argv)
    LocalOperations(args.root).initialize()
    server = create_local_server(args.root, port=args.port)
    url = f"http://127.0.0.1:{server.server_port}"
    print(f"SIFI lokal siap di {url}", flush=True)
    if args.open_browser:
        threading.Timer(0.5, lambda: webbrowser.open(url)).start()
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        pass
    finally:
        server.server_close()
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
