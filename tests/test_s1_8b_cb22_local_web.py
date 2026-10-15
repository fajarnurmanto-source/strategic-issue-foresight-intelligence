from __future__ import annotations

import json
import tempfile
import threading
import unittest
from pathlib import Path
from urllib.error import HTTPError
from urllib.request import Request, urlopen

from sifi.web.server import create_local_server


class CB22LocalWebTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(dir=Path.cwd())
        self.root = Path(self.temp.name) / "SIFI"
        (self.root / "src" / "sifi").mkdir(parents=True)
        self.server = create_local_server(self.root, port=0)
        self.thread = threading.Thread(target=self.server.serve_forever, daemon=True)
        self.thread.start()
        self.base = f"http://127.0.0.1:{self.server.server_port}"
        with urlopen(self.base + "/api/local-token", timeout=2) as response:
            self.token = json.load(response)["token"]

    def tearDown(self):
        self.server.shutdown(); self.server.server_close(); self.thread.join(timeout=2)
        self.temp.cleanup()

    def post(self, path, payload):
        return urlopen(Request(
            self.base + path, data=json.dumps(payload).encode(), method="POST",
            headers={"Content-Type":"application/json", "Origin":self.base,
                     "X-SIFI-Token":self.token},
        ), timeout=2)

    def payload(self, mode="REHEARSAL"):
        return {
            "participant_code":"P-201", "screening_record_id":"SCR-201",
            "consent_record_id":"CON-201",
            "consent_confirmed_at":"2026-10-15T08:00:00+07:00",
            "governance_authorization_id":"GOV-001",
            "representative_profile_met":True,
            "participant_is_sifi_developer":False, "mode":mode,
        }

    def test_001_status_discloses_closed_counted_gate_and_no_result(self):
        with urlopen(self.base + "/api/cb22/status", timeout=2) as response:
            data = json.load(response)
        self.assertFalse(data["gate"]["ready_for_counted_sessions"])
        self.assertEqual(data["assessment"]["result"], "NOT_READY")

    def test_002_rehearsal_is_accepted_but_not_counted(self):
        with self.post("/api/cb22/start", self.payload()) as response:
            data = json.load(response)
        self.assertEqual(data["observation"]["mode"], "REHEARSAL")
        with urlopen(self.base + "/api/cb22/status", timeout=2) as response:
            status = json.load(response)
        self.assertEqual(status["assessment"]["analyzable_participants"], 0)

    def test_003_counted_session_is_rejected_while_gate_closed(self):
        with self.assertRaises(HTTPError) as caught:
            self.post("/api/cb22/start", self.payload("COUNTED"))
        self.assertEqual(caught.exception.code, 400)
        self.assertIn("gate is closed", caught.exception.read().decode())
        caught.exception.close()


if __name__ == "__main__":
    unittest.main()
