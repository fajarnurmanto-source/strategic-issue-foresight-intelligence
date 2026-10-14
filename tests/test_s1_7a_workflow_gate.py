from __future__ import annotations

import csv
import json
import unittest
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
BASE = ROOT / "04_IMPLEMENTATION/S1_7A"


class S17AWorkflowGateTests(unittest.TestCase):
    def setUp(self):
        with (BASE / "S1_7A2_WORKFLOW_ACCEPTANCE_MATRIX_v0_1.csv").open(
            encoding="utf-8", newline=""
        ) as stream:
            self.rows = list(csv.DictReader(stream))
        self.gate = json.loads(
            (BASE / "S1_7A3_IMPLEMENTATION_GATE_RESULT_v0_1.json").read_text(
                encoding="utf-8"
            )
        )

    def test_001_all_eleven_workflow_steps_are_explicit(self):
        self.assertEqual(len(self.rows), 11)
        self.assertEqual(
            [row["step_id"] for row in self.rows],
            [f"WF-{number:02d}" for number in range(1, 12)],
        )

    def test_002_every_step_has_fail_closed_and_audit_contract(self):
        required = (
            "input", "required_output", "fail_closed_condition",
            "audit_event", "governing_boundary",
        )
        for row in self.rows:
            for field in required:
                self.assertTrue(row[field].strip(), f"{row['step_id']} missing {field}")

    def test_003_cb22_and_user_validation_remain_unclaimed(self):
        self.assertEqual(
            self.gate["cb22_state"], "PROTOCOL_ONLY_NOT_EXECUTED_NO_RESULT"
        )
        self.assertEqual(self.gate["representative_user_validation"], "NOT_PERFORMED")
        self.assertEqual(self.gate["decision_value_validation"], "NOT_PERFORMED")

    def test_004_production_and_promotion_holds_remain_false(self):
        self.assertFalse(self.gate["capability_promotion_performed"])
        self.assertFalse(self.gate["production_ingestion_authorized"])
        self.assertFalse(self.gate["production_deployment_authorized"])


if __name__ == "__main__":
    unittest.main()
