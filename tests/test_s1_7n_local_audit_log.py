from __future__ import annotations

import json
import tempfile
import threading
import unittest
from pathlib import Path
from urllib.error import HTTPError
from urllib.request import urlopen

from sifi.web.server import create_local_server


class LocalAuditLogTests(unittest.TestCase):
    def test_001_access_log_is_local_structured_rotated_and_query_free(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory) / "SIFI"
            (root / "src" / "sifi").mkdir(parents=True)
            server = create_local_server(root, port=0)
            handlers = tuple(server.audit_logger.handlers)
            self.assertEqual(len(handlers), 1)
            self.assertEqual(handlers[0].maxBytes, 1_000_000)
            self.assertEqual(handlers[0].backupCount, 3)
            thread = threading.Thread(target=server.serve_forever, daemon=True)
            thread.start()
            secret_id = "SENSITIVE-QUERY-ID"
            try:
                with self.assertRaises(HTTPError) as caught:
                    urlopen(
                        f"http://127.0.0.1:{server.server_port}/api/session?id={secret_id}",
                        timeout=2,
                    )
                self.assertEqual(caught.exception.code, 404)
                caught.exception.close()
            finally:
                server.shutdown()
                server.server_close()
                thread.join(timeout=2)
            log_path = root / "runtime" / "logs" / "web-access.jsonl"
            content = log_path.read_text(encoding="utf-8")
            record = json.loads(content.strip())
            self.assertEqual(record["path"], "/api/session")
            self.assertEqual(record["status"], 404)
            self.assertEqual(record["client"], "loopback")
            self.assertNotIn(secret_id, content)


if __name__ == "__main__":
    unittest.main()
