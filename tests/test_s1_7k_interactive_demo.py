from __future__ import annotations

import json
import tempfile
import threading
import unittest
from datetime import datetime, timezone
from pathlib import Path
from urllib.error import HTTPError
from urllib.request import Request, urlopen

from sifi.demo_workflow import DemoWorkflowError, LocalDemoWorkflow
from sifi.web.server import create_local_server


NOW = datetime(2026, 10, 15, 12, 0, tzinfo=timezone.utc)


class InteractiveDemoTests(unittest.TestCase):
    def root(self, directory):
        root = Path(directory) / "SIFI"
        (root / "src" / "sifi").mkdir(parents=True)
        return root

    def test_001_operator_completes_all_eleven_steps(self):
        with tempfile.TemporaryDirectory() as directory:
            workflow = LocalDemoWorkflow(self.root(directory))
            state = workflow.start(analyst_id="ANALYST-LOCAL", now=NOW)
            self.assertEqual(state.session.next_step.value, "WF-01")
            for sequence in range(1, 12):
                state = workflow.advance(
                    session_id=state.session.session_id,
                    analyst_id="ANALYST-LOCAL",
                    note=f"acknowledged {sequence}",
                    now=NOW,
                )
            self.assertTrue(state.session.is_complete)
            self.assertEqual(len(state.session.audit_events), 11)
            self.assertIsNotNone(state.brief)
            self.assertTrue(state.brief.analytical_only)
            self.assertFalse(state.brief.production_action_authorized)

    def test_002_wrong_operator_and_oversized_note_fail_closed(self):
        with tempfile.TemporaryDirectory() as directory:
            workflow = LocalDemoWorkflow(self.root(directory))
            state = workflow.start(analyst_id="OWNER", now=NOW)
            with self.assertRaises(DemoWorkflowError):
                workflow.advance(
                    session_id=state.session.session_id,
                    analyst_id="OTHER",
                    now=NOW,
                )
            with self.assertRaises(DemoWorkflowError):
                workflow.advance(
                    session_id=state.session.session_id,
                    analyst_id="OWNER",
                    note="x" * 1001,
                    now=NOW,
                )

    def test_003_web_mutation_requires_same_origin_token(self):
        with tempfile.TemporaryDirectory() as directory:
            root = self.root(directory)
            server = create_local_server(root, port=0)
            thread = threading.Thread(target=server.serve_forever, daemon=True)
            thread.start()
            base = f"http://127.0.0.1:{server.server_port}"
            body = json.dumps({"analyst_id": "WEB-OPERATOR"}).encode()
            try:
                unauthorized = Request(
                    base + "/api/demo/start", data=body, method="POST",
                    headers={"Content-Type": "application/json"},
                )
                with self.assertRaises(HTTPError) as caught:
                    urlopen(unauthorized, timeout=2)
                self.assertEqual(caught.exception.code, 403)
                caught.exception.close()

                with urlopen(base + "/api/local-token", timeout=2) as response:
                    token = json.load(response)["token"]
                authorized = Request(
                    base + "/api/demo/start", data=body, method="POST",
                    headers={
                        "Content-Type": "application/json",
                        "Origin": base,
                        "X-SIFI-Token": token,
                    },
                )
                with urlopen(authorized, timeout=2) as response:
                    payload = json.load(response)
                self.assertEqual(response.status, 201)
                self.assertEqual(payload["next_step"], "WF-01")
                self.assertTrue(payload["synthetic"])
            finally:
                server.shutdown()
                server.server_close()
                thread.join(timeout=2)


if __name__ == "__main__":
    unittest.main()
