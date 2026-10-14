from __future__ import annotations

import hashlib
import json
import subprocess
import unittest
from pathlib import Path

from sifi.benchmarks.harness import CanonicalBenchmarkHarness
from sifi.benchmarks.semantic_authorization import SemanticAuthorizationIssuer


ROOT = Path(__file__).resolve().parents[1]
ADJ_REL = "04_IMPLEMENTATION/S1_6D/materialized_cases/CB08.adjudication.json"


def sha256(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


class CB08AdjudicationTests(unittest.TestCase):
    def test_001_adjudication_is_not_authorization(self):
        record = json.loads((ROOT / ADJ_REL).read_text(encoding="utf-8"))
        self.assertEqual(record["adjudication_state"], "ADJUDICATED_NOT_AUTHORIZED")
        self.assertTrue(record["materialization_conformance_verified"])
        self.assertFalse(record["canonical_execution_authorized"])
        self.assertFalse(record["canonical_execution_performed"])

    def test_002_bindings_match_committed_materialization(self):
        record = json.loads((ROOT / ADJ_REL).read_text(encoding="utf-8"))
        reference = record["materialization_manifest_path"]
        committed = subprocess.check_output(
            ["git", "show", record["materialization_commit"] + ":" + reference],
            cwd=ROOT,
        )
        self.assertEqual(committed, (ROOT / reference).read_bytes())
        self.assertEqual(record["materialization_manifest_sha256"], sha256(ROOT / reference))
        self.assertEqual(record["fixture_sha256"], sha256(ROOT / record["fixture_path"]))
        self.assertEqual(record["executor_module_sha256"], sha256(ROOT / record["executor_module_path"]))

    def test_003_semantic_findings_preserve_boundaries(self):
        findings = json.loads((ROOT / ADJ_REL).read_text(encoding="utf-8"))[
            "semantic_findings"
        ]
        self.assertEqual(findings["rights_policy_z2_count"], 3)
        self.assertEqual(findings["independent_origin_count"], 2)
        self.assertFalse(findings["same_origin_repetition_counted_as_independent"])
        self.assertEqual(findings["unsupported_severity_support_count"], 0)

    def test_004_semantic_issuer_finds_cb08_eligible(self):
        harness = CanonicalBenchmarkHarness.from_stage0(ROOT)
        eligibility = SemanticAuthorizationIssuer(ROOT, harness).evaluate("CB08")
        self.assertTrue(eligibility.eligible)
        self.assertEqual(eligibility.case_id, "CB08")


if __name__ == "__main__":
    unittest.main()
