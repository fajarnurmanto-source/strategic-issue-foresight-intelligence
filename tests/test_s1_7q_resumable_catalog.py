from __future__ import annotations

import json
import tempfile
import threading
import unittest
from datetime import datetime, timezone
from pathlib import Path
from urllib.request import urlopen

from sifi.demo_workflow import LocalDemoWorkflow
from sifi.web.server import create_local_server


class ResumableCatalogTests(unittest.TestCase):
    def test_001_incomplete_session_exposes_only_explicit_resume_state(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory) / "SIFI"
            (root / "src" / "sifi").mkdir(parents=True)
            state = LocalDemoWorkflow(root).start(
                analyst_id="RESUME-OPERATOR",
                now=datetime(2026, 10, 15, 12, 0, tzinfo=timezone.utc),
            )
            server = create_local_server(root, port=0)
            thread = threading.Thread(target=server.serve_forever, daemon=True)
            thread.start()
            try:
                with urlopen(
                    f"http://127.0.0.1:{server.server_port}/api/catalog",
                    timeout=2,
                ) as response:
                    payload = json.load(response)
                summary = payload["sessions"][0]
                self.assertEqual(summary["id"], state.session.session_id)
                self.assertFalse(summary["complete"])
                self.assertEqual(summary["next_step"], "WF-01")
                self.assertNotIn("analyst_id", summary)
                self.assertNotIn("audit_events", summary)
            finally:
                server.shutdown()
                server.server_close()
                thread.join(timeout=2)


if __name__ == "__main__":
    unittest.main()
