from __future__ import annotations

import json
import tempfile
import threading
import unittest
from datetime import datetime, timezone
from pathlib import Path
from urllib.error import HTTPError
from urllib.request import Request, urlopen

from sifi.workflow import LocalWorkflowRuntime
from sifi.web.server import LocalWebBoundaryError, create_local_server


UTC = timezone.utc


class LocalWebTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.root = Path(self.temp.name) / "SIFI"
        (self.root / "src" / "sifi").mkdir(parents=True)
        self.server = create_local_server(self.root, port=0)
        self.thread = threading.Thread(target=self.server.serve_forever, daemon=True)
        self.thread.start()
        self.base = f"http://127.0.0.1:{self.server.server_port}"

    def tearDown(self):
        self.server.shutdown()
        self.server.server_close()
        self.thread.join(timeout=2)
        self.temp.cleanup()

    def request(self, path, *, method="GET", host=None):
        headers = {"Host": host} if host else {}
        return urlopen(Request(self.base + path, method=method, headers=headers), timeout=2)

    def test_001_dashboard_health_and_security_headers(self):
        with self.request("/") as response:
            body = response.read().decode()
            self.assertIn("SIFI Lokal", body)
            self.assertEqual(response.headers["X-Frame-Options"], "DENY")
            self.assertIn("default-src 'self'", response.headers["Content-Security-Policy"])
        with self.request("/health") as response:
            payload = json.load(response)
            self.assertEqual(payload["status"], "NOT_INITIALIZED")
            self.assertTrue(payload["local_only"])

    def test_002_status_and_session_are_read_only(self):
        with LocalWorkflowRuntime(
            self.server.paths.database, workspace_root=self.root
        ) as runtime:
            runtime.start_session(
                session_id="SESSION-LOCAL", task_id="TASK-1", analyst_id="A-1",
                issue_id="ISSUE-1", analytical_cutoff=datetime(2026, 1, 1, tzinfo=UTC),
                started_at=datetime(2026, 1, 2, tzinfo=UTC),
            )
        with self.request("/api/session?id=SESSION-LOCAL") as response:
            payload = json.load(response)
            self.assertEqual(payload["issue_id"], "ISSUE-1")
            self.assertTrue(payload["analytical_only"])
            self.assertFalse(payload["production_action_authorized"])
        with self.assertRaises(HTTPError) as caught:
            self.request("/api/session?id=SESSION-LOCAL", method="POST")
        self.assertEqual(caught.exception.code, 405)

    def test_003_unknown_and_invalid_requests_fail_closed(self):
        for path, expected in (("/api/session", 400), ("/api/brief?id=missing", 404), ("/../secret", 404)):
            with self.subTest(path=path), self.assertRaises(HTTPError) as caught:
                self.request(path)
            self.assertEqual(caught.exception.code, expected)
        with self.assertRaises(HTTPError) as caught:
            self.request("/health", host="example.com")
        self.assertEqual(caught.exception.code, 403)

    def test_004_non_loopback_binding_is_rejected(self):
        with self.assertRaises(LocalWebBoundaryError):
            create_local_server(self.root, host="0.0.0.0", port=0)


if __name__ == "__main__":
    unittest.main()
