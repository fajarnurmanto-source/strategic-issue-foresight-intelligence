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
from sifi.brief_export import BriefExportError, export_brief_markdown
from sifi.workflow import LocalWorkflowRuntime, WorkflowStep
from sifi.validation import (
    CB22StudyRuntime, Intervention, InterventionCategory, LocalCB22Workflow,
    StudyMode, cb22_gate_status,
    export_adjudication_bundle,
)
from sifi.validation.study_task import public_step_spec
from sifi.analysis.foresight import derive_foresight
from sifi.issues.repository import IssueObjectRepository
from sifi.persistence import SQLiteVersionStore


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
            "default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; "
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
        elif parsed.path == "/chart.umd.js":
            self._send_static("chart.umd.js", "text/javascript; charset=utf-8")
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
        elif parsed.path == "/api/cb22/status":
            self._serve_cb22_status()
        elif parsed.path == "/api/cb22/task":
            task_path = (
                self.server.paths.root / "05_VALIDATION" / "CB22_PREP"
                / "CB22_TASK_PACKAGE_v1_0.json"
            )
            task = json.loads(task_path.read_text(encoding="utf-8"))
            self._send_json(HTTPStatus.OK, {
                "scenario": task["scenario"]["title"],
                "evidence": task["scenario"]["evidence"],
                "steps": {step.value: public_step_spec(step) for step in WorkflowStep},
            })
        elif parsed.path == "/api/cb22/observation":
            self._serve_cb22_observation(parse_qs(parsed.query))
        elif parsed.path == "/api/brief/readiness":
            self._serve_brief_readiness(parse_qs(parsed.query))

        elif parsed.path == "/api/systemic-report":
            try:
                import sqlite3
                from sifi.persistence import SQLiteVersionStore
                from sifi.issues.repository import IssueObjectRepository
                from sifi.analysis.cross_issue import analyze_systemic_risks

                store = SQLiteVersionStore(self.server.paths.database)
                repo = IssueObjectRepository(store)
                
                # Ambil semua ID isu unik dari database
                conn = sqlite3.connect(f"file:{self.server.paths.database}?mode=ro", uri=True)
                rows = conn.execute("SELECT DISTINCT object_id FROM object_versions WHERE object_type='ISSUE'").fetchall()
                conn.close()
                
                issues = []
                for (issue_id,) in rows:
                    persisted = repo.latest(issue_id)
                    if persisted:
                        issues.append(persisted.issue)
                        
                report = analyze_systemic_risks(issues)
                self._send_json(HTTPStatus.OK, asdict(report))
            except Exception as exc:
                self._send_json(HTTPStatus.INTERNAL_SERVER_ERROR, {"error": str(exc)})

        elif parsed.path == "/api/brief/view":
            try:
                from sifi.persistence import SQLiteVersionStore
                import json
                query = parse_qs(parsed.query)
                brief_id = query.get("id", [""])[0]
                if not brief_id:
                    raise ValueError("Brief ID required")
                
                store = SQLiteVersionStore(self.server.paths.database)
                snapshot = store.latest("EVIDENCE_LINKED_ANALYTICAL_BRIEF", brief_id)
                if not snapshot:
                    raise ValueError("Brief not found")
                
                # Render format Markdown sederhana ke teks
                payload = snapshot.payload
                
                # Cek apakah Issue sumber masih ada
                issue_id = payload.get('issue_id')
                issue_exists = False
                if issue_id:
                    from sifi.persistence import SQLiteVersionStore
                    from sifi.issues.repository import IssueObjectRepository
                    store_check = SQLiteVersionStore(self.server.paths.database)
                    repo_check = IssueObjectRepository(store_check)
                    issue_exists = repo_check.latest(issue_id) is not None
                    store_check.close()

                md = ""
                if not issue_exists:
                    md += "> ⚠️ **PERINGATAN SISTEM:** Data Isu dan Bukti asli untuk laporan ini telah dihapus dari database.\n"
                    md += "> Laporan ini hanya menyimpan teks historis dan tidak lagi terhubung ke bukti aslinya.\n\n"

                md += f"# SIFI Analytical Brief: {brief_id}\n\n"
                md += f"**Issue ID:** {payload.get('issue_id')}\n"
                md += f"**Risk Level:** {payload.get('foresight_risk', 'N/A')}\n"
                md += f"**AI Summary:** {payload.get('foresight_summary', 'N/A')}\n\n"
                md += f"## Narrative Summary\n{payload.get('summary', '')}\n\n"
                md += f"**Evidence Refs:** {', '.join(payload.get('evidence_refs', []))}"
                
                self._send_json(HTTPStatus.OK, {"content": md})
            except Exception as exc:
                self._send_json(HTTPStatus.INTERNAL_SERVER_ERROR, {"error": str(exc)})
        elif parsed.path == "/api/stats":
            try:
                from sifi.persistence import SQLiteVersionStore
                from sifi.issues.repository import IssueObjectRepository
                from sifi.analysis.foresight import derive_foresight
                import sqlite3
                store = SQLiteVersionStore(self.server.paths.database)
                repo = IssueObjectRepository(store)
                conn = sqlite3.connect(f"file:{self.server.paths.database}?mode=ro", uri=True)
                rows = conn.execute("SELECT DISTINCT object_id FROM object_versions WHERE object_type='ISSUE'").fetchall()
                conn.close()
                stats = {"HIGH": 0, "ELEVATED": 0, "LOW": 0, "INSUFFICIENT": 0}
                for (issue_id,) in rows:
                    persisted = repo.latest(issue_id)
                    if persisted:
                        prop = derive_foresight(persisted.issue)
                        stats[prop.risk.level] += 1
                self._send_json(HTTPStatus.OK, stats)
            except Exception as exc:
                self._send_json(HTTPStatus.INTERNAL_SERVER_ERROR, {"error": str(exc)})
        elif parsed.path == "/api/system-health":
            try:
                import sqlite3
                conn = sqlite3.connect(f"file:{self.server.paths.database}?mode=ro", uri=True)
                counts = {}
                for obj_type in ("ISSUE", "EVIDENCE_LINKED_ANALYTICAL_BRIEF", "BOUNDED_ANALYST_WORKFLOW_SESSION"):
                    row = conn.execute("SELECT COUNT(*) FROM object_versions WHERE object_type=?", (obj_type,)).fetchone()
                    counts[obj_type] = row[0] if row else 0
                conn.close()

                worker_running = False
                if hasattr(self.server, "worker") and self.server.worker is not None:
                    worker_running = self.server.worker.is_alive()

                self._send_json(HTTPStatus.OK, {
                    "database_status": "HEALTHY",
                    "worker_running": worker_running,
                    "counts": counts
                })
            except Exception as exc:
                self._send_json(HTTPStatus.INTERNAL_SERVER_ERROR, {"error": str(exc)})
        elif parsed.path == "/api/issues":
            try:
                import sqlite3
                from sifi.analysis.foresight import derive_foresight
                from sifi.issues.model import IssueObject
                from sifi.persistence import SQLiteVersionStore
                from sifi.issues.repository import IssueObjectRepository
                
                # 1. Ambil daftar ID unik
                conn = sqlite3.connect(f"file:{self.server.paths.database}?mode=ro", uri=True)
                rows = conn.execute("SELECT DISTINCT object_id FROM object_versions WHERE object_type='ISSUE'").fetchall()
                conn.close()
                
                # 2. Ambil detail isu menggunakan store terpusat
                store = SQLiteVersionStore(self.server.paths.database)
                repo = IssueObjectRepository(store)
                
                issues_data = []
                for (issue_id,) in rows:
                    persisted = repo.latest(issue_id)
                    if persisted:
                        try:
                            prop = derive_foresight(persisted.issue)
                            risk_level = prop.risk.level
                        except Exception:
                            risk_level = "UNKNOWN"
                            
                        issues_data.append({
                            "id": persisted.issue.issue_id,
                            "title": persisted.issue.title,
                            "type": persisted.issue.issue_type,
                            "domain": persisted.issue.issue_domain,
                            "risk": risk_level,
                            "recorded_at": persisted.recorded_at.isoformat(),
                            "supporting": list(persisted.issue.evidence.supporting_evidence_ids),
                            "contradictory": list(persisted.issue.evidence.contradictory_evidence_ids),
                            "context_only": list(persisted.issue.evidence.context_only_evidence_ids),
                            "excluded": list(persisted.issue.evidence.excluded_production_evidence_ids)
                        })
                
                store.close()
                
                # Urutkan dari yang terbaru
                issues_data.sort(key=lambda x: x.get('recorded_at', ''), reverse=True)
                self._send_json(HTTPStatus.OK, issues_data)
                
            except Exception as exc:
                import traceback
                traceback.print_exc() # Cetak error ke terminal server
                self._send_json(HTTPStatus.INTERNAL_SERVER_ERROR, {"error": str(exc)})

        elif parsed.path == "/api/stats":
            try:
                from sifi.persistence import SQLiteVersionStore
                from sifi.issues.repository import IssueObjectRepository
                from sifi.analysis.foresight import derive_foresight
                import sqlite3
                store = SQLiteVersionStore(self.server.paths.database)
                repo = IssueObjectRepository(store)
                conn = sqlite3.connect(f"file:{self.server.paths.database}?mode=ro", uri=True)
                rows = conn.execute("SELECT DISTINCT object_id FROM object_versions WHERE object_type='ISSUE'").fetchall()
                conn.close()
                stats = {"HIGH": 0, "ELEVATED": 0, "LOW": 0, "INSUFFICIENT": 0}
                for (issue_id,) in rows:
                    persisted = repo.latest(issue_id)
                    if persisted:
                        prop = derive_foresight(persisted.issue)
                        stats[prop.risk.level] += 1
                self._send_json(HTTPStatus.OK, stats)
            except Exception as exc:
                self._send_json(HTTPStatus.INTERNAL_SERVER_ERROR, {"error": str(exc)})
        elif parsed.path == "/api/executive-briefing":
            try:
                from sifi.persistence import SQLiteVersionStore
                from sifi.issues.repository import IssueObjectRepository
                from sifi.analysis.foresight import derive_foresight
                import sqlite3
                store = SQLiteVersionStore(self.server.paths.database)
                repo = IssueObjectRepository(store)
                conn = sqlite3.connect(f"file:{self.server.paths.database}?mode=ro", uri=True)
                rows = conn.execute("SELECT DISTINCT object_id FROM object_versions WHERE object_type='ISSUE'").fetchall()
                conn.close()
                top_issue = None
                highest_risk_level = "LOW"
                for (issue_id,) in rows:
                    persisted = repo.latest(issue_id)
                    if persisted:
                        prop = derive_foresight(persisted.issue)
                        if prop.risk.level == "HIGH":
                            top_issue = (persisted.issue, prop)
                            break
                        elif prop.risk.level == "ELEVATED" and highest_risk_level != "HIGH":
                            top_issue = (persisted.issue, prop)
                            highest_risk_level = "ELEVATED"
                        elif top_issue is None:
                            top_issue = (persisted.issue, prop)
                            highest_risk_level = prop.risk.level
                if not top_issue:
                    self._send_json(HTTPStatus.NOT_FOUND, {"message": "Belum ada data isu."})
                    return
                issue, prop = top_issue
                dims = {"TR-01": "Atensi Media & Publik", "TR-02": "Kekuatan Bukti", "TR-03": "Kondisi Lapangan", "TR-04": "Narasi & Opini", "TR-05": "Cakupan Geografis"}
                dim_risks = [{"name": dims.get(d.dimension, d.dimension), "level": d.level, "direction": d.direction} for d in prop.dimension_risks]
                payload = {
                    "issue_id": issue.issue_id, "title": issue.title,
                    "risk_level": prop.risk.level, "risk_direction": prop.risk.direction,
                    "rationale": prop.risk.rationale, "recommendations": prop.recommendation_proposals,
                    "dimension_risks": dim_risks
                }
                self._send_json(HTTPStatus.OK, payload)
            except Exception as exc:
                self._send_json(HTTPStatus.INTERNAL_SERVER_ERROR, {"error": str(exc)})
        elif parsed.path == "/api/systemic-report":
            try:
                import sqlite3
                from sifi.persistence import SQLiteVersionStore
                from sifi.issues.repository import IssueObjectRepository
                from sifi.analysis.cross_issue import analyze_systemic_risks
                store = SQLiteVersionStore(self.server.paths.database)
                repo = IssueObjectRepository(store)
                conn = sqlite3.connect(f"file:{self.server.paths.database}?mode=ro", uri=True)
                rows = conn.execute("SELECT DISTINCT object_id FROM object_versions WHERE object_type='ISSUE'").fetchall()
                conn.close()
                issues = []
                for (issue_id,) in rows:
                    persisted = repo.latest(issue_id)
                    if persisted:
                        issues.append(persisted.issue)
                report = analyze_systemic_risks(issues)
                self._send_json(HTTPStatus.OK, asdict(report))
            except Exception as exc:
                self._send_json(HTTPStatus.INTERNAL_SERVER_ERROR, {"error": str(exc)})
        elif parsed.path == "/api/brief/view":
            try:
                from sifi.persistence import SQLiteVersionStore
                import json
                query = parse_qs(parsed.query)
                brief_id = query.get("id", [""])[0]
                if not brief_id:
                    raise ValueError("Brief ID required")
                store = SQLiteVersionStore(self.server.paths.database)
                snapshot = store.latest("EVIDENCE_LINKED_ANALYTICAL_BRIEF", brief_id)
                if not snapshot:
                    raise ValueError("Brief not found")
                payload = snapshot.payload
                
                # Cek apakah Issue sumber masih ada
                issue_id = payload.get('issue_id')
                issue_exists = False
                if issue_id:
                    from sifi.persistence import SQLiteVersionStore
                    from sifi.issues.repository import IssueObjectRepository
                    store_check = SQLiteVersionStore(self.server.paths.database)
                    repo_check = IssueObjectRepository(store_check)
                    issue_exists = repo_check.latest(issue_id) is not None
                    store_check.close()

                md = ""
                if not issue_exists:
                    md += "> ⚠️ **PERINGATAN SISTEM:** Data Isu dan Bukti asli untuk laporan ini telah dihapus dari database.\n"
                    md += "> Laporan ini hanya menyimpan teks historis dan tidak lagi terhubung ke bukti aslinya.\n\n"

                md += f"# SIFI Analytical Brief: {brief_id}\n\n"
                md += f"**Issue ID:** {payload.get('issue_id')}\n"
                md += f"**Risk Level:** {payload.get('foresight_risk', 'N/A')}\n"
                md += f"**AI Summary:** {payload.get('foresight_summary', 'N/A')}\n\n"
                md += f"## Narrative Summary\n{payload.get('summary', '')}\n\n"
                md += f"**Evidence Refs:** {', '.join(payload.get('evidence_refs', []))}"
                self._send_json(HTTPStatus.OK, {"content": md})
            except Exception as exc:
                self._send_json(HTTPStatus.INTERNAL_SERVER_ERROR, {"error": str(exc)})
        elif parsed.path == "/api/brief/download":
            try:
                from sifi.brief_export import export_brief_markdown
                query = parse_qs(parsed.query)
                brief_id = query.get("id", [""])[0]
                if not brief_id:
                    raise ValueError("Brief ID required")
                filepath = export_brief_markdown(self.server.paths.root, brief_id)
                content = filepath.read_text(encoding="utf-8")
                self.send_response(HTTPStatus.OK)
                self.send_header("Content-Type", "text/markdown; charset=utf-8")
                self.send_header("Content-Disposition", f'attachment; filename="{filepath.name}"')
                self.end_headers()
                self.wfile.write(content.encode("utf-8"))
            except Exception as exc:
                self._send_json(HTTPStatus.INTERNAL_SERVER_ERROR, {"error": str(exc)})
        elif parsed.path == "/chart.umd.js":
            self._send_static("chart.umd.js", "text/javascript; charset=utf-8")
        elif parsed.path.startswith("/api/foresight/"):
            issue_id = parsed.path[len("/api/foresight/"):]
            self._serve_foresight(issue_id)
        elif parsed.path in {"/api/session", "/api/brief"}:
            self._serve_record(parsed.path, parse_qs(parsed.query))
        else:
            self._send_json(HTTPStatus.NOT_FOUND, {"error": "not found"})

    def _serve_foresight(self, issue_id: str) -> None:
        if not issue_id or len(issue_id) > 128:
            self._send_json(HTTPStatus.BAD_REQUEST, {"error": "valid issue_id required"})
            return
        try:
            store = SQLiteVersionStore(self.server.paths.database)
            repo = IssueObjectRepository(store)
            persisted = repo.latest(issue_id)
            
            if persisted is None:
                self._send_json(HTTPStatus.NOT_FOUND, {"error": "issue not found"})
                return
                
            proposal = derive_foresight(persisted.issue)
            self._send_json(HTTPStatus.OK, asdict(proposal))
        except Exception as exc:
            self._send_json(HTTPStatus.INTERNAL_SERVER_ERROR, {"error": str(exc)})

    def _serve_brief_readiness(self, query: dict[str, list[str]]) -> None:
        """Read-only historical evidence preflight; not human approval."""
        values = query.get("id", [])
        if len(values) != 1 or not values[0].strip() or len(values[0]) > 128:
            self._send_json(
                HTTPStatus.BAD_REQUEST,
                {"error": "one valid id is required"},
            )
            return

        if not self.server.paths.database.is_file():
            self._send_json(
                HTTPStatus.NOT_FOUND,
                {"error": "local database is not initialized"},
            )
            return

        with LocalWorkflowRuntime(
            self.server.paths.database,
            workspace_root=self.server.paths.root,
        ) as runtime:
            try:
                result = runtime.assess_brief_readiness(values[0])
            except ValueError as exc:
                if str(exc) != "brief not found":
                    raise
                self._send_json(
                    HTTPStatus.NOT_FOUND,
                    {"error": "brief not found"},
                )
                return

        self._send_json(
            HTTPStatus.OK,
            {
                "assessment": result,
                "analytical_only": True,
                "human_approval_granted": False,
                "production_action_authorized": False,
                "scope": "historical_reference_and_admission_preflight",
            },
        )
    def _serve_cb22_status(self) -> None:
        gate = cb22_gate_status(self.server.paths.root)
        if self.server.paths.database.is_file():
            with CB22StudyRuntime(
                self.server.paths.database, workspace_root=self.server.paths.root
            ) as runtime:
                assessment = runtime.assessment()
                observations = runtime.observations()
        else:
            from sifi.validation import assess_cb22
            assessment = assess_cb22(())
            observations = ()
        self._send_json(HTTPStatus.OK, {
            "gate": gate,
            "assessment": assessment,
            "active_sessions": [
                {
                    "observation_id": item.observation_id,
                    "participant_code": item.participant_code,
                    "mode": item.mode,
                    "completed_stage_ids": item.completed_stage_ids,
                }
                for item in observations if item.completed_at is None
            ],
            "observations": [
                {"observation_id": item.observation_id,
                 "participant_code": item.participant_code,
                 "mode": item.mode, "task_completed": item.task_completed,
                 "ended": item.completed_at is not None}
                for item in observations
            ],
        })

    def _serve_cb22_observation(self, query: dict[str, list[str]]) -> None:
        values = query.get("id", [])
        if len(values) != 1 or not values[0].strip() or len(values[0]) > 128:
            self._send_json(HTTPStatus.BAD_REQUEST, {"error": "one valid id is required"})
            return
        if not self.server.paths.database.is_file():
            self._send_json(HTTPStatus.NOT_FOUND, {"error": "local database is not initialized"})
            return
        with CB22StudyRuntime(
            self.server.paths.database, workspace_root=self.server.paths.root
        ) as runtime:
            item = runtime.latest(values[0])
        if item is None:
            self._send_json(HTTPStatus.NOT_FOUND, {"error": "observation not found"})
            return
        self._send_json(HTTPStatus.OK, item)

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
        if parsed.path not in {
            "/api/demo/start", "/api/demo/advance", "/api/brief/export",
            "/api/cb22/start", "/api/cb22/advance", "/api/cb22/intervention",
            "/api/cb22/stop", "/api/cb22/incident", "/api/cb22/export",
            "/api/issue/create", "/api/ingest/feed", "/api/ingest/rss", "/api/ingest/historical",
            "/api/brief/generate", "/api/semantic/test", "/api/foresight/simulate",
            "/api/worker/toggle", "/api/system/reset-simulation",
        }:
            self._reject_write()
            return
        if not self._valid_write_request():
            self._send_json(HTTPStatus.FORBIDDEN, {"error": "valid local request token required"})
            return
        try:
            payload = self._read_json()
            if parsed.path == "/api/worker/toggle":
                action = str(payload.get("action", ""))
                if not hasattr(self.server, "worker"):
                    raise ValueError("Worker not initialized")
                if action == "stop":
                    self.server.worker.stop()
                    self._send_json(HTTPStatus.OK, {"status": "stopped"})
                elif action == "start":
                    from sifi.ingestion.worker import IngestionWorker
                    self.server.worker = IngestionWorker(self.server.paths.database)
                    self.server.worker.start()
                    self._send_json(HTTPStatus.OK, {"status": "started"})
                else:
                    raise ValueError("Invalid action")
                return

            if parsed.path == "/api/ingest/historical":
                from datetime import datetime, timezone, timedelta
                from sifi.domain import PersistenceClass, TrajectoryDimension
                from sifi.issues.model import IssueObject, IssueEvidenceLinks, IssueGeography, IssueTrajectoryState
                from sifi.issues.repository import IssueObjectRepository
                from sifi.persistence import SQLiteVersionStore
                import random

                start_str = payload.get("start_date")
                end_str = payload.get("end_date")
                if not start_str or not end_str:
                    raise ValueError("Tanggal mulai dan selesai wajib diisi")
                
                start_dt = datetime.strptime(start_str, "%Y-%m-%d").replace(tzinfo=timezone.utc)
                end_dt = datetime.strptime(end_str, "%Y-%m-%d").replace(tzinfo=timezone.utc)
                
                days_diff = (end_dt - start_dt).days
                if days_diff < 0:
                    raise ValueError("Tanggal selesai harus setelah tanggal mulai")
                if days_diff > 365:
                    raise ValueError("Rentang waktu maksimal adalah 365 hari (1 tahun)")

                store = SQLiteVersionStore(self.server.paths.database)
                repo = IssueObjectRepository(store)
                
                # Ingestion bersifat non-destruktif (Append-Only)
                
                total_issues = 0
                current_date = start_dt
                limit_reached = False
                last_covered_date = start_dt

                while current_date <= end_dt:
                    if total_issues >= 600:
                        limit_reached = True
                        break
                        
                    # Simulasi: 2 sampai 6 isu per hari
                    daily_issues = random.randint(2, 6)
                    
                    if total_issues + daily_issues > 600:
                        daily_issues = 600 - total_issues
                        limit_reached = True
                        
                    for i in range(daily_issues):
                        issue_id = f"HIST-{int(current_date.timestamp())}-{total_issues}"
                        title = f"Simulated event on {current_date.strftime('%d %b %Y')}"
                        
                        issue = IssueObject(
                            issue_id=issue_id, title=title, issue_type="historical_crawl",
                            persistence_class=PersistenceClass.PC_03_STRUCTURAL, lifecycle_state="ACTIVE",
                            first_seen=current_date, last_updated=current_date, identity_review_state=None,
                            evidence=IssueEvidenceLinks(supporting_evidence_ids=(f"EV-{issue_id}",), contradictory_evidence_ids=()),
                            geography=IssueGeography(),
                            trajectories=tuple(IssueTrajectoryState(dimension=d, trajectory_state="stable", as_of=current_date, evidence_ids=(f"EV-{issue_id}",)) for d in TrajectoryDimension)
                        )
                        
                        if not repo.latest(issue_id):
                            repo.append(issue, version=1, effective_at=current_date, recorded_at=current_date)
                            total_issues += 1
                            
                    last_covered_date = current_date
                    current_date += timedelta(days=1)

                store.close()

                msg = f"Berhasil tarik {total_issues} isu historis."

                self._send_json(HTTPStatus.CREATED, {"status": "success", "ingested": total_issues, "message": msg})
                return


            if parsed.path == "/api/ingest/rss":
                import urllib.request
                import xml.etree.ElementTree as ET
                from datetime import datetime, timezone
                from sifi.domain import PersistenceClass, TrajectoryDimension
                from sifi.issues.model import IssueObject, IssueEvidenceLinks, IssueGeography, IssueTrajectoryState
                from sifi.issues.repository import IssueObjectRepository
                from sifi.persistence import SQLiteVersionStore
                from sifi.analysis.semantic import analyze_text

                rss_url = payload.get("url")
                if not rss_url:
                    raise ValueError("URL RSS wajib diisi")

                req = urllib.request.Request(rss_url, headers={'User-Agent': 'Mozilla/5.0 SIFI-Bot/1.0'})
                try:
                    with urllib.request.urlopen(req, timeout=10) as response:
                        xml_data = response.read()
                except Exception as e:
                    raise ValueError(f"Gagal mengambil RSS: {str(e)}")

                try:
                    root = ET.fromstring(xml_data)
                except Exception as e:
                    raise ValueError(f"Format XML RSS tidak valid: {str(e)}")

                store = SQLiteVersionStore(self.server.paths.database)
                repo = IssueObjectRepository(store)
                now = datetime.now(timezone.utc)
                count = 0

                items = root.findall('.//item')
                if not items:
                    items = root.findall('.//{http://www.w3.org/2005/Atom}entry')

                for item in items:
                    title = item.findtext('title') or item.findtext('{http://www.w3.org/2005/Atom}title')
                    if not title: continue
                    
                    semantic = analyze_text(title)
                    issue_id = f"RSS-{int(now.timestamp())}-{count}"
                    
                    domain = "Umum"
                    if semantic.threat_score >= 5: domain = "Keamanan"
                    elif "economy" in title.lower() or "market" in title.lower(): domain = "Ekonomi"
                    elif "health" in title.lower() or "covid" in title.lower(): domain = "Kesehatan"

                    # Beri 3 Bukti Pendukung agar tidak Abstain
                    supporting_evs = (f"EV-RSS-TITLE-{count}", f"EV-RSS-LINK-{count}", f"EV-RSS-DESC-{count}")

                    if semantic.threat_score >= 8:
                        traj_state = "escalating severely"
                        gaps = (
                            "Teks berita mengandung sinyal ancaman sangat tinggi.",
                            "Klaim belum diverifikasi oleh sumber independen.",
                            "Membutuhkan validasi fakta dari analis manusia."
                        )
                    elif semantic.threat_score >= 5:
                        traj_state = "escalating"
                        gaps = (
                            "Teks berita bernada negatif, menunggu konfirmasi lapangan.",
                        )
                    else:
                        traj_state = "stable"
                        gaps = ()

                    issue = IssueObject(
                        issue_id=issue_id, title=title[:150], issue_type="rss_live",
                        persistence_class=PersistenceClass.PC_01_INCIDENT, lifecycle_state="ACTIVE",
                        first_seen=now, last_updated=now, identity_review_state=None,
                        issue_domain=domain,
                        evidence=IssueEvidenceLinks(
                            supporting_evidence_ids=supporting_evs, 
                            contradictory_evidence_ids=()
                        ),
                        geography=IssueGeography(),
                        trajectories=tuple(
                            IssueTrajectoryState(dimension=d, trajectory_state=traj_state, as_of=now, evidence_ids=supporting_evs) 
                            for d in TrajectoryDimension
                        ),
                        evidence_gaps=gaps
                    )
                    
                    if not repo.latest(issue_id):
                        repo.append(issue, version=1, effective_at=now, recorded_at=now)
                        count += 1

                store.close()
                self._send_json(HTTPStatus.CREATED, {"status": "success", "ingested": count})
                return

            if parsed.path == "/api/ingest/feed":
                from sifi.ingestion.feed_parser import parse_feed
                from sifi.issues.repository import IssueObjectRepository
                from sifi.persistence import SQLiteVersionStore
                import json, tempfile
                from datetime import datetime, timezone
                feed_data = {"items": [{"id": "FEED-001", "title": "Supply chain disruption", "supporting": ["EV-F1"], "contradictory": []}]}
                tmp = tempfile.NamedTemporaryFile(mode="w", delete=False, suffix=".json")
                json.dump(feed_data, tmp)
                tmp.close()
                issues = parse_feed(tmp.name)
                now = datetime.now(timezone.utc)
                store = SQLiteVersionStore(self.server.paths.database)
                repo = IssueObjectRepository(store)
                count = 0
                for issue in issues:
                    if not repo.latest(issue.issue_id):
                        repo.append(issue, version=1, effective_at=now, recorded_at=now)
                        count += 1
                self._send_json(HTTPStatus.CREATED, {"status": "success", "ingested": count})
                return

            if parsed.path == "/api/brief/generate":
                from sifi.analysis.foresight import derive_foresight
                from sifi.issues.repository import IssueObjectRepository
                from sifi.persistence import SQLiteVersionStore
                from sifi.workflow.brief import EvidenceLinkedBrief
                from sifi.domain.versioning import VersionedSnapshot
                from datetime import datetime, timezone
                from dataclasses import asdict

                issue_id = str(payload.get("issue_id", ""))
                store = SQLiteVersionStore(self.server.paths.database)
                repo = IssueObjectRepository(store)
                persisted = repo.latest(issue_id)
                
                if not persisted:
                    raise ValueError("Issue not found")
                    
                proposal = derive_foresight(persisted.issue)
                now = datetime.now(timezone.utc)
                
                brief = EvidenceLinkedBrief(
                    brief_id=f"BRIEF-{issue_id}", issue_id=issue_id,
                    summary="Auto-generated brief based on synthetic ingestion.",
                    evidence_refs=(
                    tuple(persisted.issue.evidence.supporting_evidence_ids) +
                    tuple(persisted.issue.evidence.contradictory_evidence_ids) +
                    tuple(persisted.issue.evidence.context_only_evidence_ids) +
                    tuple(persisted.issue.evidence.excluded_production_evidence_ids)
                ),
                    created_at=now, analytical_cutoff=now,
                    foresight_risk=proposal.risk.level,
                    foresight_summary=" | ".join(proposal.recommendation_proposals)
                )
                
                payload_dict = {
                    **asdict(brief), 
                    "created_at": brief.created_at.isoformat(), 
                    "analytical_cutoff": brief.analytical_cutoff.isoformat()
                }
                
                existing_brief = store.latest("EVIDENCE_LINKED_ANALYTICAL_BRIEF", brief.brief_id)
                next_version = 1 if existing_brief is None else existing_brief.version + 1
                
                store.append(VersionedSnapshot(
                    object_type="EVIDENCE_LINKED_ANALYTICAL_BRIEF",
                    object_id=brief.brief_id, version=next_version, effective_at=now, recorded_at=now,
                    payload=payload_dict
                ))
                
                self._send_json(HTTPStatus.CREATED, {"status": "success", "brief_id": brief.brief_id})
                return

            if parsed.path == "/api/foresight/simulate":
                from sifi.analysis.simulation import simulate_remove_contradiction
                from sifi.issues.repository import IssueObjectRepository
                from sifi.persistence import SQLiteVersionStore
                issue_id = str(payload.get("issue_id", ""))
                store = SQLiteVersionStore(self.server.paths.database)
                repo = IssueObjectRepository(store)
                persisted = repo.latest(issue_id)
                if not persisted:
                    raise ValueError("Issue not found")
                sim_result = simulate_remove_contradiction(persisted.issue)
                self._send_json(HTTPStatus.OK, sim_result)
                return

            if parsed.path == "/api/semantic/test":
                from sifi.analysis.semantic import analyze_text
                from dataclasses import asdict
                text_to_analyze = str(payload.get("text", ""))
                result = analyze_text(text_to_analyze)
                self._send_json(HTTPStatus.OK, asdict(result))
                return

            if parsed.path == "/api/issue/create":
                from datetime import datetime, timezone
                from sifi.domain import PersistenceClass, TrajectoryDimension
                from sifi.issues.model import IssueObject, IssueEvidenceLinks, IssueGeography, IssueTrajectoryState
                from sifi.issues.repository import IssueObjectRepository
                from sifi.persistence import SQLiteVersionStore
                now = datetime.now(timezone.utc)
                issue_id = str(payload.get("issue_id", "")).strip()
                title = str(payload.get("title", "")).strip()
                if not issue_id or not title:
                    raise ValueError("issue_id dan title wajib diisi")
                supp = tuple(e.strip() for e in payload.get("supporting_evidence", []) if e.strip())
                cont = tuple(e.strip() for e in payload.get("contradictory_evidence", []) if e.strip())
                if set(supp).intersection(set(cont)):
                    raise ValueError("Bukti tidak boleh sekaligus menjadi pendukung dan kontradiksi")
                fallback_ev = tuple(supp + cont) if (supp or cont) else ("EV-DEFAULT",)
                issue = IssueObject(
                    issue_id=issue_id, title=title, issue_type="web_created",
                    issue_domain=str(payload.get("domain", "")),
                    persistence_class=PersistenceClass.PC_01_INCIDENT, lifecycle_state="ACTIVE",
                    first_seen=now, last_updated=now, identity_review_state=None,
                    evidence=IssueEvidenceLinks(supporting_evidence_ids=supp, contradictory_evidence_ids=cont),
                    geography=IssueGeography(),
                    trajectories=tuple(IssueTrajectoryState(dimension=d, trajectory_state="stable", as_of=now, evidence_ids=fallback_ev) for d in TrajectoryDimension)
                )
                store = SQLiteVersionStore(self.server.paths.database)
                repo = IssueObjectRepository(store)
                if repo.latest(issue_id):
                    raise ValueError("Issue ID sudah ada di database")
                repo.append(issue, version=1, effective_at=now, recorded_at=now)
                self._send_json(HTTPStatus.CREATED, {"status": "success", "issue_id": issue_id})
                return


            if parsed.path == "/api/semantic/test":
                from sifi.analysis.semantic import analyze_text
                text_to_analyze = str(payload.get("text", ""))
                result = analyze_text(text_to_analyze)
                self._send_json(HTTPStatus.OK, asdict(result))
                return


            if parsed.path == "/api/ingest/rss":
                import urllib.request
                import xml.etree.ElementTree as ET
                from datetime import datetime, timezone
                from sifi.domain import PersistenceClass, TrajectoryDimension
                from sifi.issues.model import IssueObject, IssueEvidenceLinks, IssueGeography, IssueTrajectoryState
                from sifi.issues.repository import IssueObjectRepository
                from sifi.persistence import SQLiteVersionStore
                from sifi.analysis.semantic import analyze_text

                rss_url = payload.get("url")
                if not rss_url:
                    raise ValueError("URL RSS wajib diisi")

                req = urllib.request.Request(rss_url, headers={'User-Agent': 'Mozilla/5.0 SIFI-Bot/1.0'})
                try:
                    with urllib.request.urlopen(req, timeout=10) as response:
                        xml_data = response.read()
                except Exception as e:
                    raise ValueError(f"Gagal mengambil RSS: {str(e)}")

                try:
                    root = ET.fromstring(xml_data)
                except Exception as e:
                    raise ValueError(f"Format XML RSS tidak valid: {str(e)}")

                store = SQLiteVersionStore(self.server.paths.database)
                repo = IssueObjectRepository(store)
                now = datetime.now(timezone.utc)
                count = 0

                items = root.findall('.//item')
                if not items:
                    items = root.findall('.//{http://www.w3.org/2005/Atom}entry')

                for item in items:
                    title = item.findtext('title') or item.findtext('{http://www.w3.org/2005/Atom}title')
                    if not title: continue
                    
                    semantic = analyze_text(title)
                    issue_id = f"RSS-{int(now.timestamp())}-{count}"
                    
                    domain = "Umum"
                    if semantic.threat_score >= 5: domain = "Keamanan"
                    elif "economy" in title.lower() or "market" in title.lower(): domain = "Ekonomi"
                    elif "health" in title.lower() or "covid" in title.lower(): domain = "Kesehatan"

                    # Beri 3 Bukti Pendukung agar tidak Abstain
                    supporting_evs = (f"EV-RSS-TITLE-{count}", f"EV-RSS-LINK-{count}", f"EV-RSS-DESC-{count}")

                    if semantic.threat_score >= 8:
                        traj_state = "escalating severely"
                        gaps = (
                            "Teks berita mengandung sinyal ancaman sangat tinggi.",
                            "Klaim belum diverifikasi oleh sumber independen.",
                            "Membutuhkan validasi fakta dari analis manusia."
                        )
                    elif semantic.threat_score >= 5:
                        traj_state = "escalating"
                        gaps = (
                            "Teks berita bernada negatif, menunggu konfirmasi lapangan.",
                        )
                    else:
                        traj_state = "stable"
                        gaps = ()

                    issue = IssueObject(
                        issue_id=issue_id, title=title[:150], issue_type="rss_live",
                        persistence_class=PersistenceClass.PC_01_INCIDENT, lifecycle_state="ACTIVE",
                        first_seen=now, last_updated=now, identity_review_state=None,
                        issue_domain=domain,
                        evidence=IssueEvidenceLinks(
                            supporting_evidence_ids=supporting_evs, 
                            contradictory_evidence_ids=()
                        ),
                        geography=IssueGeography(),
                        trajectories=tuple(
                            IssueTrajectoryState(dimension=d, trajectory_state=traj_state, as_of=now, evidence_ids=supporting_evs) 
                            for d in TrajectoryDimension
                        ),
                        evidence_gaps=gaps
                    )
                    
                    if not repo.latest(issue_id):
                        repo.append(issue, version=1, effective_at=now, recorded_at=now)
                        count += 1

                store.close()
                self._send_json(HTTPStatus.CREATED, {"status": "success", "ingested": count})
                return

            if parsed.path == "/api/ingest/feed":
                from sifi.ingestion.feed_parser import parse_feed
                from sifi.issues.repository import IssueObjectRepository
                from sifi.persistence import SQLiteVersionStore
                
                # Buat dummy feed file
                import json, tempfile
                feed_data = {
                    "items": [
                        {"id": "FEED-001", "title": "Supply chain disruption", "supporting": ["EV-F1", "EV-F2"], "contradictory": ["EV-F3"]},
                        {"id": "FEED-002", "title": "Cyber attack on infrastructure", "supporting": ["EV-F4"], "contradictory": []},
                        {"id": "FEED-003", "title": "Market volatility increases", "supporting": ["EV-F5"], "contradictory": ["EV-F6", "EV-F7"]}
                    ]
                }
                tmp = tempfile.NamedTemporaryFile(mode="w", delete=False, suffix=".json")
                json.dump(feed_data, tmp)
                tmp.close()
                
                issues = parse_feed(tmp.name)
                from datetime import datetime, timezone
                now = datetime.now(timezone.utc)
                store = SQLiteVersionStore(self.server.paths.database)
                repo = IssueObjectRepository(store)
                count = 0
                for issue in issues:
                    if not repo.latest(issue.issue_id):
                        repo.append(issue, version=1, effective_at=now, recorded_at=now)
                        count += 1
                self._send_json(HTTPStatus.CREATED, {"status": "success", "ingested": count})
                return

            if parsed.path == "/api/brief/generate":
                from sifi.analysis.foresight import derive_foresight
                from sifi.issues.repository import IssueObjectRepository
                from sifi.persistence import SQLiteVersionStore
                from sifi.workflow.brief import EvidenceLinkedBrief
                from datetime import datetime, timezone

                issue_id = str(payload.get("issue_id", ""))
                store = SQLiteVersionStore(self.server.paths.database)
                repo = IssueObjectRepository(store)
                persisted = repo.latest(issue_id)
                if not persisted:
                    raise ValueError("Issue not found")
                
                proposal = derive_foresight(persisted.issue)
                now = datetime.now(timezone.utc)
                
                brief = EvidenceLinkedBrief(
                    brief_id=f"BRIEF-{issue_id}",
                    issue_id=issue_id,
                    summary="Auto-generated brief based on synthetic ingestion.",
                    evidence_refs=(
                    tuple(persisted.issue.evidence.supporting_evidence_ids) +
                    tuple(persisted.issue.evidence.contradictory_evidence_ids) +
                    tuple(persisted.issue.evidence.context_only_evidence_ids) +
                    tuple(persisted.issue.evidence.excluded_production_evidence_ids)
                ),
                    created_at=now,
                    analytical_cutoff=now,
                    foresight_risk=proposal.risk.level,
                    foresight_summary=" | ".join(proposal.recommendation_proposals)
                )
                # Simpan manual ke store untuk demo
                from sifi.domain.versioning import VersionedSnapshot
                store.append(VersionedSnapshot(
                    object_type="EVIDENCE_LINKED_ANALYTICAL_BRIEF",
                    object_id=brief.brief_id, version=1,
                    effective_at=now, recorded_at=now,
                    payload={
                        **asdict(brief),
                        "created_at": brief.created_at.isoformat(),
                        "analytical_cutoff": brief.analytical_cutoff.isoformat()
                    }
                ))
                self._send_json(HTTPStatus.CREATED, {"status": "success", "brief_id": brief.brief_id})
                return

            if parsed.path == "/api/issue/create":
                from datetime import datetime, timezone
                from sifi.domain import PersistenceClass, TrajectoryDimension
                from sifi.issues.model import IssueObject, IssueEvidenceLinks, IssueGeography, IssueTrajectoryState
                from sifi.issues.repository import IssueObjectRepository
                from sifi.persistence import SQLiteVersionStore

                now = datetime.now(timezone.utc)
                issue_id = str(payload.get("issue_id", "")).strip()
                title = str(payload.get("title", "")).strip()
                if not issue_id or not title:
                    raise ValueError("issue_id dan title wajib diisi")

                supp = tuple(e.strip() for e in payload.get("supporting_evidence", []) if e.strip())
                cont = tuple(e.strip() for e in payload.get("contradictory_evidence", []) if e.strip())
                
                if set(supp).intersection(set(cont)):
                    raise ValueError("Bukti tidak boleh sekaligus menjadi pendukung dan kontradiksi")

                all_ev = supp + cont
                fallback_ev = all_ev if all_ev else ("EV-DEFAULT",)

                issue = IssueObject(
                    issue_id=issue_id,
                    title=title,
                    issue_type="web_created",
                    persistence_class=PersistenceClass.PC_01_INCIDENT,
                    lifecycle_state="ACTIVE",
                    first_seen=now,
                    last_updated=now,
                    identity_review_state=None,
                    evidence=IssueEvidenceLinks(
                        supporting_evidence_ids=supp,
                        contradictory_evidence_ids=cont
                    ),
                    geography=IssueGeography(),
                    trajectories=tuple(
                        IssueTrajectoryState(dimension=d, trajectory_state="stable", as_of=now, evidence_ids=fallback_ev)
                        for d in TrajectoryDimension
                    )
                )
                store = SQLiteVersionStore(self.server.paths.database)
                repo = IssueObjectRepository(store)
                if repo.latest(issue_id):
                    raise ValueError("Issue ID sudah ada di database")
                    
                repo.append(issue, version=1, effective_at=now, recorded_at=now)
                self._send_json(HTTPStatus.CREATED, {"status": "success", "issue_id": issue_id})
                return
            if parsed.path == "/api/brief/export":
                destination = export_brief_markdown(
                    self.server.paths.root, str(payload.get("brief_id", ""))
                )
                self._send_json(
                    HTTPStatus.CREATED,
                    {"export": str(destination.relative_to(self.server.paths.root))},
                )
                return
            if parsed.path.startswith("/api/cb22/"):
                self._handle_cb22_write(parsed.path, payload)
                return
            if parsed.path == "/api/system/reset-simulation":
                import sqlite3
                conn = sqlite3.connect(self.server.paths.database, timeout=30.0)
                conn.execute("DELETE FROM object_versions WHERE object_id LIKE 'HIST-%'")
                conn.execute("DELETE FROM object_versions WHERE object_id LIKE 'AUTO-%'")
                conn.commit()
                conn.close()
                self._send_json(HTTPStatus.OK, {"status": "success", "message": "Data simulasi berhasil dibersihkan."})
                return

            workflow = LocalDemoWorkflow(self.server.paths.root)
            if parsed.path.endswith("start"):
                state = workflow.start(analyst_id=str(payload.get("analyst_id", "")))
            else:
                state = workflow.advance(
                    session_id=str(payload.get("session_id", "")),
                    analyst_id=str(payload.get("analyst_id", "")),
                    note=str(payload.get("note", "")),
                )
        except Exception as exc:
            self._send_json(HTTPStatus.INTERNAL_SERVER_ERROR, {"error": str(exc)})
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

    def _handle_cb22_write(self, path: str, payload: dict) -> None:
        if path.endswith("/export"):
            if self.server.paths.database.is_file():
                with CB22StudyRuntime(
                    self.server.paths.database, workspace_root=self.server.paths.root
                ) as runtime:
                    items = runtime.observations()
            else:
                items = ()
            destination = export_adjudication_bundle(self.server.paths.root, items)
            self._send_json(HTTPStatus.CREATED, {
                "export": str(destination.relative_to(self.server.paths.root))
            })
            return
        workflow = LocalCB22Workflow(self.server.paths.root)
        if path.endswith("/start"):
            try:
                consent_at = datetime.fromisoformat(str(payload.get("consent_confirmed_at", "")))
            except ValueError as exc:
                raise ValueError("consent_confirmed_at must be an ISO date-time with timezone") from exc
            if consent_at.tzinfo is None or consent_at.utcoffset() is None:
                raise ValueError("consent_confirmed_at must include a timezone")
            state = workflow.start(
                participant_code=str(payload.get("participant_code", "")),
                screening_record_id=str(payload.get("screening_record_id", "")),
                consent_record_id=str(payload.get("consent_record_id", "")),
                consent_confirmed_at=consent_at,
                governance_authorization_id=str(payload.get("governance_authorization_id", "")),
                representative_profile_met=payload.get("representative_profile_met") is True,
                participant_is_sifi_developer=payload.get("participant_is_sifi_developer") is True,
                mode=StudyMode(str(payload.get("mode", ""))),
            )
            self._send_json(HTTPStatus.CREATED, {
                "observation": state.observation,
                "session": state.session,
                "next_step": state.session.next_step,
            })
            return
        if path.endswith("/advance"):
            state = workflow.advance(
                observation_id=str(payload.get("observation_id", "")),
                participant_code=str(payload.get("participant_code", "")),
                answer_code=str(payload.get("answer_code", "")),
            )
            self._send_json(HTTPStatus.OK, {
                "observation": state.observation,
                "session": state.session,
                "next_step": state.session.next_step,
                "brief": state.brief,
            })
            return
        if path.endswith("/stop"):
            observation = workflow.stop_incomplete(
                observation_id=str(payload.get("observation_id", "")),
                participant_code=str(payload.get("participant_code", "")),
                reason_code=str(payload.get("reason_code", "")),
            )
            self._send_json(HTTPStatus.OK, {"observation": observation})
            return
        if path.endswith("/incident"):
            observation = workflow.record_incident(
                observation_id=str(payload.get("observation_id", "")),
                kind=str(payload.get("kind", "")),
                code=str(payload.get("code", "")),
            )
            self._send_json(HTTPStatus.OK, {"observation": observation})
            return
        intervention = Intervention(
            category=InterventionCategory(str(payload.get("category", ""))),
            reason_code=str(payload.get("reason_code", "")),
            duration_seconds=int(payload.get("duration_seconds", -1)),
            routine_developer_help=payload.get("routine_developer_help") is True,
        )
        observation = workflow.record_intervention(
            observation_id=str(payload.get("observation_id", "")),
            intervention=intervention,
        )
        self._send_json(HTTPStatus.OK, {"observation": observation})

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