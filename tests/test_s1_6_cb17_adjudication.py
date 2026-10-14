from __future__ import annotations

import hashlib
import json
import subprocess
import unittest
from pathlib import Path

from sifi.benchmarks.harness import CanonicalBenchmarkHarness
from sifi.benchmarks.semantic_authorization import SemanticAuthorizationIssuer


ROOT = Path(__file__).resolve().parents[1]
ADJ_REL = "04_IMPLEMENTATION/S1_6D/materialized_cases/CB17.adjudication.json"


def sha256(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


class CB17AdjudicationTests(unittest.TestCase):
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
            ["git", "show", record["materialization_commit"] + ":" + reference], cwd=ROOT
        )
        self.assertEqual(committed, (ROOT / reference).read_bytes())
        self.assertEqual(record["materialization_manifest_sha256"], sha256(ROOT / reference))
        self.assertEqual(record["fixture_sha256"], sha256(ROOT / record["fixture_path"]))
        self.assertEqual(
            record["executor_module_sha256"], sha256(ROOT / record["executor_module_path"])
        )

    def test_003_semantic_findings_preserve_disagreement(self):
        findings = json.loads((ROOT / ADJ_REL).read_text(encoding="utf-8"))["semantic_findings"]
        self.assertTrue(findings["material_disagreement_explicit"])
        self.assertTrue(findings["both_prior_reviews_preserved"])
        self.assertTrue(findings["adjudication_with_dissent_retained"])
        self.assertFalse(findings["first_reviewer_selected_automatically"])
        self.assertFalse(findings["reviewer_judgments_averaged"])

    def test_004_semantic_issuer_finds_cb17_eligible(self):
        harness = CanonicalBenchmarkHarness.from_stage0(ROOT)
        eligibility = SemanticAuthorizationIssuer(ROOT, harness).evaluate("CB17")
        self.assertTrue(eligibility.eligible)
        self.assertEqual(eligibility.case_id, "CB17")


if __name__ == "__main__":
    unittest.main()
