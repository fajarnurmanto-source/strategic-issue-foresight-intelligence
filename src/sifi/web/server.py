from __future__ import annotations

import json
import logging
import secrets
import sys
from dataclasses import asdict, is_dataclass
from datetime import datetime
from enum import Enum
from http import HTTPStatus
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from logging.handlers import RotatingFileHandler
from pathlib import Path
from urllib.parse import parse_qs, urlsplit

from sifi.local import LocalSIFIPaths
from sifi.local_ops import LocalOperations
from sifi.demo_workflow import DemoWorkflowError, LocalDemoWorkflow
from sifi.workflow import LocalWorkflowRuntime


class LocalWebBoundaryError(ValueError):
    pass


def _json_value(value):
    if is_dataclass(value):
        return {key: _json_value(item) for key, item in asdict(value).items()}
    if isinstance(value, datetime):
        return value.isoformat()
    if isinstance(value, Enum):
        return value.value
    if isinstance(value, (tuple, list)):
        return [_json_value(item) for item in value]
    if isinstance(value, dict):
        return {str(key): _json_value(item) for key, item in value.items()}
    return value


class LocalSIFIWebServer(ThreadingHTTPServer):
    daemon_threads = True
    allow_reuse_address = True

    def __init__(self, server_address, handler_class, *, workspace_root: Path):
        host, _port = server_address
        if host not in {"127.0.0.1", "localhost"}:
            raise LocalWebBoundaryError("SIFI web server must bind to loopback only")
        self.paths = LocalSIFIPaths.from_root(workspace_root)
        self.static_root = Path(__file__).with_name("static").resolve()
        self.csrf_token = secrets.token_urlsafe(32)
        self.audit_logger = logging.getLogger(f"sifi.local.web.{id(self)}")
        self.audit_logger.setLevel(logging.INFO)
        self.audit_logger.propagate = False
        handler = RotatingFileHandler(
            self.paths.logs / "web-access.jsonl",
            maxBytes=1_000_000,
            backupCount=3,
            encoding="utf-8",
        )
        handler.setFormatter(logging.Formatter("%(message)s"))
        self.audit_logger.addHandler(handler)
        super().__init__(server_address, handler_class)

    def server_close(self) -> None:
        super().server_close()
        for handler in tuple(self.audit_logger.handlers):
            handler.close()
            self.audit_logger.removeHandler(handler)

    def handle_error(self, request, client_address) -> None:
        error_type = sys.exc_info()[0]
        self.audit_logger.error(
            json.dumps(
                {
                    "event": "request_error",
                    "error_type": error_type.__name__ if error_type else "UNKNOWN",
                    "client": "loopback",
                },
                sort_keys=True,
            )
        )


class LocalSIFIRequestHandler(BaseHTTPRequestHandler):
    server: LocalSIFIWebServer
    server_version = "SIFI-Local/0.1"
    sys_version = ""

    def log_message(self, format, *args):
        return

    def send_response(self, code, message=None):
        super().send_response(code, message)
        self.server.audit_logger.info(
            json.dumps(
                {
                    "at": datetime.now().astimezone().isoformat(),
                    "method": self.command,
                    "path": urlsplit(self.path).path,
                    "status": int(code),
                    "client": "loopback",
                },
                sort_keys=True,
            )
        )

    def _headers(self, status: HTTPStatus, content_type: str, length: int) -> None:
        self.send_response(status)
        self.send_header("Content-Type", content_type)
        self.send_header("Content-Length", str(length))
        self.send_header("Cache-Control", "no-store")
        self.send_header("X-Content-Type-Options", "nosniff")
        self.send_header("X-Frame-Options", "DENY")
        self.send_header("Referrer-Policy", "no-referrer")
        self.send_header(
            "Content-Security-Policy",
            "default-src 'self'; style-src 'self'; script-src 'self'; "
            "img-src 'self' data:; connect-src 'self'; frame-ancestors 'none'",
        )
        self.end_headers()

    def _send_json(self, status: HTTPStatus, payload) -> None:
        body = json.dumps(_json_value(payload), sort_keys=True).encode("utf-8")
        self._headers(status, "application/json; charset=utf-8", len(body))
        self.wfile.write(body)

    def _send_static(self, filename: str, content_type: str) -> None:
        path = (self.server.static_root / filename).resolve()
        if not path.is_relative_to(self.server.static_root) or not path.is_file():
            self._send_json(HTTPStatus.NOT_FOUND, {"error": "not found"})
            return
        body = path.read_bytes()
        self._headers(HTTPStatus.OK, content_type, len(body))
        self.wfile.write(body)

    def _valid_host(self) -> bool:
        raw = self.headers.get("Host", "")
        hostname = raw.rsplit(":", 1)[0].strip("[]").lower()
        return hostname in {"127.0.0.1", "localhost"}

    def do_GET(self) -> None:
        if not self._valid_host():
            self._send_json(HTTPStatus.FORBIDDEN, {"error": "local host required"})
            return
        parsed = urlsplit(self.path)
        if parsed.path == "/":
            self._send_static("index.html", "text/html; charset=utf-8")
        elif parsed.path == "/styles.css":
            self._send_static("styles.css", "text/css; charset=utf-8")
        elif parsed.path == "/app.js":
            self._send_static("app.js", "text/javascript; charset=utf-8")
        elif parsed.path == "/health":
            status = LocalOperations(self.server.paths.root).status()
            code = HTTPStatus.OK if status.integrity in {"OK", "NOT_INITIALIZED"} else HTTPStatus.SERVICE_UNAVAILABLE
            self._send_json(code, {"service": "sifi-local", "status": status.integrity, "local_only": True})
        elif parsed.path == "/api/status":
            self._send_json(HTTPStatus.OK, LocalOperations(self.server.paths.root).status())
        elif parsed.path == "/api/local-token":
            self._send_json(HTTPStatus.OK, {"token": self.server.csrf_token})
        elif parsed.path == "/api/catalog":
            self._serve_catalog()
        elif parsed.path in {"/api/session", "/api/brief"}:
            self._serve_record(parsed.path, parse_qs(parsed.query))
        else:
            self._send_json(HTTPStatus.NOT_FOUND, {"error": "not found"})

    def _serve_catalog(self) -> None:
        if not self.server.paths.database.is_file():
            self._send_json(HTTPStatus.OK, {"sessions": [], "briefs": []})
            return
        with LocalWorkflowRuntime(
            self.server.paths.database, workspace_root=self.server.paths.root
        ) as runtime:
            catalog = runtime.catalog(limit=100)
        self._send_json(
            HTTPStatus.OK,
            {
                "sessions": [
                    {
                        "id": item.session_id,
                        "issue_id": item.issue_id,
                        "complete": item.is_complete,
                        "next_step": item.next_step,
                        "analytical_only": item.analytical_only,
                    }
                    for item in catalog["sessions"]
                ],
                "briefs": [
                    {
                        "id": item.brief_id,
                        "issue_id": item.issue_id,
                        "created_at": item.created_at,
                        "analytical_only": item.analytical_only,
                    }
                    for item in catalog["briefs"]
                ],
            },
        )

    def _serve_record(self, path: str, query: dict[str, list[str]]) -> None:
        values = query.get("id", [])
        if len(values) != 1 or not values[0].strip() or len(values[0]) > 128:
            self._send_json(HTTPStatus.BAD_REQUEST, {"error": "one valid id is required"})
            return
        if not self.server.paths.database.is_file():
            self._send_json(HTTPStatus.NOT_FOUND, {"error": "local database is not initialized"})
            return
        with LocalWorkflowRuntime(
            self.server.paths.database, workspace_root=self.server.paths.root
        ) as runtime:
            item = runtime.latest(values[0]) if path.endswith("session") else runtime.latest_brief(values[0])
        if item is None:
            self._send_json(HTTPStatus.NOT_FOUND, {"error": "record not found"})
            return
        self._send_json(HTTPStatus.OK, item)

    def do_HEAD(self) -> None:
        self._headers(HTTPStatus.METHOD_NOT_ALLOWED, "application/json; charset=utf-8", 0)

    def _valid_write_request(self) -> bool:
        origin = self.headers.get("Origin", "")
        valid_origins = {
            f"http://127.0.0.1:{self.server.server_port}",
            f"http://localhost:{self.server.server_port}",
        }
        return (
            self._valid_host()
            and origin in valid_origins
            and secrets.compare_digest(
                self.headers.get("X-SIFI-Token", ""), self.server.csrf_token
            )
            and self.headers.get_content_type() == "application/json"
        )

    def _read_json(self) -> dict:
        try:
            length = int(self.headers.get("Content-Length", "0"))
        except ValueError as exc:
            raise DemoWorkflowError("invalid content length") from exc
        if length < 2 or length > 8192:
            raise DemoWorkflowError("request body must contain 2 to 8192 bytes")
        try:
            payload = json.loads(self.rfile.read(length))
        except (UnicodeDecodeError, json.JSONDecodeError) as exc:
            raise DemoWorkflowError("request body must be valid JSON") from exc
        if not isinstance(payload, dict):
            raise DemoWorkflowError("request body must be an object")
        return payload

    def do_POST(self) -> None:
        parsed = urlsplit(self.path)
        if parsed.path not in {"/api/demo/start", "/api/demo/advance"}:
            self._reject_write()
            return
        if not self._valid_write_request():
            self._send_json(HTTPStatus.FORBIDDEN, {"error": "valid local request token required"})
            return
        try:
            payload = self._read_json()
            workflow = LocalDemoWorkflow(self.server.paths.root)
            if parsed.path.endswith("start"):
                state = workflow.start(analyst_id=str(payload.get("analyst_id", "")))
            else:
                state = workflow.advance(
                    session_id=str(payload.get("session_id", "")),
                    analyst_id=str(payload.get("analyst_id", "")),
                    note=str(payload.get("note", "")),
                )
        except DemoWorkflowError as exc:
            self._send_json(HTTPStatus.BAD_REQUEST, {"error": str(exc)})
            return
        self._send_json(
            HTTPStatus.CREATED if parsed.path.endswith("start") else HTTPStatus.OK,
            {
                "session": state.session,
                "next_step": state.session.next_step,
                "brief": state.brief,
                "synthetic": state.synthetic,
            },
        )

    def _reject_write(self) -> None:
        self._send_json(HTTPStatus.METHOD_NOT_ALLOWED, {"error": "local dashboard is read-only"})

    do_PUT = _reject_write
    do_PATCH = _reject_write
    do_DELETE = _reject_write


def create_local_server(
    root: str | Path, *, host: str = "127.0.0.1", port: int = 8765
) -> LocalSIFIWebServer:
    paths = LocalSIFIPaths.from_root(root)
    paths.initialize()
    return LocalSIFIWebServer(
        (host, port), LocalSIFIRequestHandler, workspace_root=paths.root
    )
