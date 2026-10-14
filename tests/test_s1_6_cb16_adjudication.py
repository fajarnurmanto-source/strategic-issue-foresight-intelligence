from __future__ import annotations

import hashlib
import json
import subprocess
import unittest
from pathlib import Path

from sifi.benchmarks.harness import CanonicalBenchmarkHarness
from sifi.benchmarks.semantic_authorization import SemanticAuthorizationIssuer


ROOT = Path(__file__).resolve().parents[1]
ADJ_REL = "04_IMPLEMENTATION/S1_6D/materialized_cases/CB16.adjudication.json"


def sha256(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


class CB16AdjudicationTests(unittest.TestCase):
    def test_001_adjudication_is_not_authorization(self):
        record = json.loads((ROOT / ADJ_REL).read_text(encoding="utf-8"))
        self.assertEqual(
            record["adjudication_state"], "ADJUDICATED_NOT_AUTHORIZED"
        )
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
        self.assertEqual(
            record["materialization_manifest_sha256"], sha256(ROOT / reference)
        )
        self.assertEqual(
            record["fixture_sha256"], sha256(ROOT / record["fixture_path"])
        )
        self.assertEqual(
            record["executor_module_sha256"],
            sha256(ROOT / record["executor_module_path"]),
        )

    def test_003_semantic_findings_preserve_correction_contract(self):
        findings = json.loads((ROOT / ADJ_REL).read_text(encoding="utf-8"))[
            "semantic_findings"
        ]
        self.assertTrue(findings["correction_lineage_preserved"])
        self.assertTrue(findings["reviewer_identity_and_role_preserved"])
        self.assertTrue(findings["correction_rationale_preserved"])
        self.assertTrue(findings["prior_state_preserved"])
        self.assertFalse(findings["silent_overwrite_observed"])
        self.assertTrue(findings["analytical_boundary_preserved"])

    def test_004_semantic_issuer_finds_cb16_eligible(self):
        harness = CanonicalBenchmarkHarness.from_stage0(ROOT)
        eligibility = SemanticAuthorizationIssuer(ROOT, harness).evaluate("CB16")
        self.assertTrue(eligibility.eligible)
        self.assertEqual(eligibility.case_id, "CB16")


if __name__ == "__main__":
    unittest.main()
