from __future__ import annotations

import json
import tempfile
import threading
import unittest
from pathlib import Path
from urllib.error import HTTPError
from urllib.request import Request, urlopen

from sifi.web.server import create_local_server
from sifi.validation.study_task import STUDY_STEP_SPECS
from sifi.workflow import WorkflowStep


class CB22LocalWebTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(dir=Path.cwd())
        self.root = Path(self.temp.name) / "SIFI"
        (self.root / "src" / "sifi").mkdir(parents=True)
        task_dir = self.root / "05_VALIDATION" / "CB22_PREP"
        task_dir.mkdir(parents=True)
        source = Path.cwd() / "05_VALIDATION" / "CB22_PREP" / "CB22_TASK_PACKAGE_v1_0.json"
        (task_dir / source.name).write_bytes(source.read_bytes())
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

    def test_004_adjudication_bundle_exports_inside_workspace(self):
        with self.post("/api/cb22/export", {}) as response:
            payload = json.load(response)
        self.assertTrue((self.root / payload["export"]).is_file())

    def test_005_task_endpoint_exposes_fixed_evidence_and_structured_choices(self):
        with urlopen(self.base + "/api/cb22/task", timeout=2) as response:
            payload = json.load(response)
        self.assertEqual(len(payload["evidence"]), 5)
        self.assertIn("WF-01", payload["steps"])
        self.assertGreaterEqual(len(payload["steps"]["WF-01"]["options"]), 2)

    def test_006_web_rehearsal_completes_all_structured_steps_and_stays_excluded(self):
        with self.post("/api/cb22/start", self.payload()) as response:
            state = json.load(response)
        for _ in range(11):
            step = WorkflowStep(state["next_step"])
            with self.post("/api/cb22/advance", {
                "observation_id":state["observation"]["observation_id"],
                "participant_code":state["observation"]["participant_code"],
                "answer_code":STUDY_STEP_SPECS[step].accepted_code,
            }) as response:
                state = json.load(response)
        self.assertIsNone(state["next_step"])
        self.assertTrue(state["observation"]["task_completed"])
        with urlopen(self.base + "/api/cb22/status", timeout=2) as response:
            status = json.load(response)
        self.assertEqual(status["assessment"]["result"], "NOT_READY")
        self.assertEqual(status["assessment"]["analyzable_participants"], 0)
        observation_id = state["observation"]["observation_id"]
        with urlopen(self.base + "/api/cb22/observation?id=" + observation_id, timeout=2) as response:
            observation = json.load(response)
        self.assertEqual(observation["completed_stage_ids"][-1], "WF-11")


if __name__ == "__main__":
    unittest.main()
