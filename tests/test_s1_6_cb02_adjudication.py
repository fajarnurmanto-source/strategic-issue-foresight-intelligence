from __future__ import annotations

import hashlib
import json
import subprocess
import unittest
from pathlib import Path

from sifi.benchmarks.harness import CanonicalBenchmarkHarness
from sifi.benchmarks.semantic_authorization import (
    SemanticAuthorizationIssuer,
)


ROOT = Path(__file__).resolve().parents[1]
ADJ_REL = (
    "04_IMPLEMENTATION/S1_6D/materialized_cases/CB02.adjudication.json"
)


def sha256(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


class CB02AdjudicationTests(unittest.TestCase):
    def test_001_adjudication_is_conformant_but_not_authorized(self):
        payload = json.loads((ROOT / ADJ_REL).read_text(encoding="utf-8"))

        self.assertEqual(payload["case_id"], "CB02")
        self.assertEqual(
            payload["adjudication_state"],
            "ADJUDICATED_NOT_AUTHORIZED",
        )
        self.assertEqual(
            payload["decision"],
            "MATERIALIZATION_CONFORMS_TO_CANONICAL_CASE_CONTRACT",
        )
        self.assertFalse(payload["canonical_execution_authorized"])
        self.assertFalse(payload["canonical_execution_performed"])
        self.assertFalse(payload["canonical_result_recorded"])

    def test_002_adjudication_binds_committed_materialization(self):
        payload = json.loads((ROOT / ADJ_REL).read_text(encoding="utf-8"))
        reference = payload["materialization_manifest_path"]
        committed = subprocess.run(
            [
                "git",
                "show",
                payload["materialization_commit"] + ":" + reference,
            ],
            cwd=ROOT,
            stdout=subprocess.PIPE,
            check=True,
        ).stdout

        self.assertEqual(committed, (ROOT / reference).read_bytes())
        self.assertEqual(
            payload["materialization_manifest_sha256"],
            sha256(ROOT / reference),
        )
        self.assertEqual(
            payload["fixture_sha256"],
            sha256(ROOT / payload["fixture_path"]),
        )
        self.assertEqual(
            payload["executor_module_sha256"],
            sha256(ROOT / payload["executor_module_path"]),
        )

    def test_003_semantic_issuer_finds_cb02_eligible(self):
        harness = CanonicalBenchmarkHarness.from_stage0(ROOT)
        eligibility = SemanticAuthorizationIssuer(ROOT, harness).evaluate(
            "CB02"
        )

        self.assertTrue(eligibility.eligible)
        self.assertEqual(eligibility.case_id, "CB02")


if __name__ == "__main__":
    unittest.main()
