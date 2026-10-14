from __future__ import annotations

import json
import unittest
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
CLOSURE = ROOT / "04_IMPLEMENTATION/S1_6D/S1_6D16_PHASE_CLOSURE_RESULT_v0_1.json"


class S16DPhaseClosureTests(unittest.TestCase):
    def test_001_exact_materializable_partition_is_recorded(self):
        record = json.loads(CLOSURE.read_text(encoding="utf-8"))
        expected = {"CB01", "CB02", "CB07", "CB08", "CB16", "CB17", "CB20", "CB21"}
        self.assertEqual(set(record["executed_case_ids"]), expected)
        self.assertEqual(record["s1_6c_materializable_now_count"], 8)
        self.assertEqual(record["canonical_result_recorded_count"], 8)
        self.assertEqual(record["pass_count"], 8)
        self.assertEqual(record["fail_count"], 0)

    def test_002_every_result_artifact_records_pass(self):
        record = json.loads(CLOSURE.read_text(encoding="utf-8"))
        for case_id, reference in record["result_artifacts"].items():
            payload = json.loads((ROOT / reference).read_text(encoding="utf-8"))
            if "cases" in payload:
                case = next(item for item in payload["cases"] if item["case_id"] == case_id)
                self.assertEqual(case["result_state"], "PASS")
            else:
                self.assertEqual(payload["case_id"], case_id)
                self.assertEqual(payload["result_state"], "PASS")

    def test_003_unexecuted_partition_remains_explicit(self):
        record = json.loads(CLOSURE.read_text(encoding="utf-8"))
        unexecuted = (
            record["blocked_missing_capability_case_ids"]
            + record["blocked_rights_fixture_case_ids"]
            + record["blocked_reference_materialization_case_ids"]
            + record["protocol_only_case_ids"]
        )
        self.assertEqual(len(unexecuted), 16)
        self.assertFalse(set(unexecuted) & set(record["executed_case_ids"]))

    def test_004_production_and_promotion_remain_false(self):
        record = json.loads(CLOSURE.read_text(encoding="utf-8"))
        self.assertFalse(record["capability_promotion_performed"])
        self.assertFalse(record["production_ingestion_authorized"])
        self.assertFalse(record["production_deployment_authorized"])
        self.assertEqual(record["next_roadmap_stage"], "S1.7_BOUNDED_ANALYST_WORKFLOW")


if __name__ == "__main__":
    unittest.main()
