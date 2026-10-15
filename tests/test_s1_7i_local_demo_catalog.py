from __future__ import annotations

import json
import tempfile
import threading
import unittest
from pathlib import Path
from urllib.request import urlopen

from sifi.demo import DEMO_BRIEF_ID, DEMO_SESSION_ID, seed_local_demo
from sifi.web.server import create_local_server
from sifi.workflow import LocalWorkflowRuntime


class LocalDemoCatalogTests(unittest.TestCase):
    def root(self, directory):
        root = Path(directory) / "SIFI"
        (root / "src" / "sifi").mkdir(parents=True)
        return root

    def test_001_demo_is_complete_synthetic_and_idempotent(self):
        with tempfile.TemporaryDirectory() as directory:
            root = self.root(directory)
            first = seed_local_demo(root)
            second = seed_local_demo(root)
            self.assertTrue(first.created)
            self.assertFalse(second.created)
            self.assertTrue(first.synthetic)
            with LocalWorkflowRuntime(root / "runtime/data/sifi.sqlite", workspace_root=root) as runtime:
                session = runtime.latest(DEMO_SESSION_ID)
                brief = runtime.latest_brief(DEMO_BRIEF_ID)
                self.assertTrue(session.is_complete)
                self.assertEqual(len(session.audit_events), 11)
                self.assertTrue(brief.analytical_only)
                self.assertFalse(brief.production_action_authorized)

    def test_002_catalog_lists_latest_records_only(self):
        with tempfile.TemporaryDirectory() as directory:
            root = self.root(directory)
            seed_local_demo(root)
            with LocalWorkflowRuntime(root / "runtime/data/sifi.sqlite", workspace_root=root) as runtime:
                catalog = runtime.catalog()
                self.assertEqual(tuple(item.session_id for item in catalog["sessions"]), (DEMO_SESSION_ID,))
                self.assertEqual(tuple(item.brief_id for item in catalog["briefs"]), (DEMO_BRIEF_ID,))

    def test_003_web_catalog_exposes_bounded_summaries(self):
        with tempfile.TemporaryDirectory() as directory:
            root = self.root(directory)
            seed_local_demo(root)
            server = create_local_server(root, port=0)
            thread = threading.Thread(target=server.serve_forever, daemon=True)
            thread.start()
            try:
                with urlopen(f"http://127.0.0.1:{server.server_port}/api/catalog", timeout=2) as response:
                    payload = json.load(response)
                self.assertEqual(payload["sessions"][0]["id"], DEMO_SESSION_ID)
                self.assertEqual(payload["briefs"][0]["id"], DEMO_BRIEF_ID)
                self.assertTrue(payload["sessions"][0]["complete"])
                self.assertIsNone(payload["sessions"][0]["next_step"])
                self.assertTrue(payload["sessions"][0]["analytical_only"])
                self.assertNotIn("summary", payload["briefs"][0])
            finally:
                server.shutdown()
                server.server_close()
                thread.join(timeout=2)


if __name__ == "__main__":
    unittest.main()
