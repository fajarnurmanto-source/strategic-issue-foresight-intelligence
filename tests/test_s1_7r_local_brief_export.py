from __future__ import annotations

import json
import os
import tempfile
import threading
import unittest
from pathlib import Path
from urllib.error import HTTPError
from urllib.request import Request, urlopen

from sifi.brief_export import BriefExportError, export_brief_markdown
from sifi.demo import DEMO_BRIEF_ID, seed_local_demo
from sifi.web.server import create_local_server


class LocalBriefExportTests(unittest.TestCase):
    def root(self, directory):
        root = Path(directory) / "SIFI"
        (root / "src" / "sifi").mkdir(parents=True)
        return root

    def test_001_markdown_export_is_atomic_bounded_and_analytical(self):
        with tempfile.TemporaryDirectory() as directory:
            root = self.root(directory)
            seed_local_demo(root)
            exported = export_brief_markdown(root, DEMO_BRIEF_ID)
            self.assertEqual(
                os.path.normcase(os.path.realpath(exported.parent)),
                os.path.normcase(
                    os.path.realpath(root / "runtime" / "exports")
                ),
            )
            content = exported.read_text(encoding="utf-8")
            self.assertIn("analytical only:** `true`", content.lower())
            self.assertIn("production action authorized:** `false`", content.lower())
            self.assertIn("DEMO-EV-001", content)
            self.assertFalse(any(exported.parent.glob("*.tmp")))

    def test_002_unknown_brief_fails_without_export(self):
        with tempfile.TemporaryDirectory() as directory:
            root = self.root(directory)
            seed_local_demo(root)
            with self.assertRaises(BriefExportError):
                export_brief_markdown(root, "../../outside")
            self.assertEqual(tuple((root / "runtime" / "exports").iterdir()), ())

    def test_003_web_export_requires_local_token_and_returns_relative_path(self):
        with tempfile.TemporaryDirectory() as directory:
            root = self.root(directory)
            seed_local_demo(root)
            server = create_local_server(root, port=0)
            thread = threading.Thread(target=server.serve_forever, daemon=True)
            thread.start()
            base = f"http://127.0.0.1:{server.server_port}"
            body = json.dumps({"brief_id": DEMO_BRIEF_ID}).encode()
            try:
                with urlopen(base + "/api/local-token", timeout=2) as response:
                    token = json.load(response)["token"]
                request = Request(
                    base + "/api/brief/export", data=body, method="POST",
                    headers={"Content-Type":"application/json", "Origin":base, "X-SIFI-Token":token},
                )
                with urlopen(request, timeout=2) as response:
                    payload = json.load(response)
                self.assertEqual(response.status, 201)
                self.assertTrue(payload["export"].startswith("runtime\\exports\\") or payload["export"].startswith("runtime/exports/"))
                self.assertFalse(Path(payload["export"]).is_absolute())
            finally:
                server.shutdown()
                server.server_close()
                thread.join(timeout=2)


if __name__ == "__main__":
    unittest.main()
