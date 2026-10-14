from __future__ import annotations

import hashlib
import json
import unittest
from pathlib import Path
from types import SimpleNamespace

from sifi.benchmarks.harness import CanonicalBenchmarkHarness
from sifi.benchmarks.materialized_cases_batch3 import (
    BATCH3_EXECUTORS,
    BATCH3_EXECUTOR_SPECS,
)
from sifi.benchmarks.model import HarnessExecutionState


ROOT = Path(__file__).resolve().parents[1]


def sha256(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


class CB08MaterializationTests(unittest.TestCase):
    def test_001_component_self_check(self):
        observation = BATCH3_EXECUTORS["CB08"](
            SimpleNamespace(case_id="CB08")
        )
        self.assertTrue(observation.expected_conditions_satisfied)
        self.assertFalse(observation.forbidden_output_observed)
        self.assertTrue(observation.regression_dependencies_satisfied)
        self.assertTrue(observation.rights_constraints_satisfied)
        self.assertIn("rights_policy_z2_count=3", observation.notes)
        self.assertIn("independent_origin_count=2", observation.notes)
        self.assertIn(
            "unsupported_severity_support_count=0",
            observation.notes,
        )

    def test_002_manifest_hash_bindings(self):
        spec = BATCH3_EXECUTOR_SPECS["CB08"]
        manifest = json.loads(
            (ROOT / spec.manifest_path).read_text(encoding="utf-8")
        )
        self.assertEqual(
            manifest["fixture_sha256"],
            sha256(ROOT / spec.fixture_path),
        )
        self.assertEqual(
            manifest["executor_module_sha256"],
            sha256(ROOT / manifest["executor_module_path"]),
        )
        self.assertEqual(
            manifest["materialization_state"],
            "MATERIALIZED_NOT_ADJUDICATED",
        )
        self.assertFalse(manifest["canonical_execution_authorized"])

    def test_003_wrong_case_binding_is_rejected(self):
        with self.assertRaises(ValueError):
            BATCH3_EXECUTORS["CB08"](SimpleNamespace(case_id="CB99"))

    def test_004_registration_does_not_authorize_execution(self):
        harness = CanonicalBenchmarkHarness.from_stage0(ROOT)
        spec = BATCH3_EXECUTOR_SPECS["CB08"]
        harness.register_executor(
            "CB08",
            BATCH3_EXECUTORS["CB08"],
            executor_id=spec.executor_id,
            executor_version=spec.executor_version,
            executor_reference=spec.executor_reference,
        )
        self.assertFalse(harness.has_execution_authorization("CB08"))
        self.assertEqual(
            harness.run_case("CB08").execution_state,
            HarnessExecutionState.NOT_READY_FOR_EXECUTION,
        )

    def test_005_definition_binding_is_preserved(self):
        definition = CanonicalBenchmarkHarness.from_stage0(ROOT).definition(
            "CB08"
        )
        self.assertEqual(
            (
                definition.rights_basis,
                definition.reference_methodology,
            ),
            (
                "Z2_REQUIRED_FOR_PRODUCTION_TEST",
                "ADJUDICATED_INDEPENDENCE_REFERENCE",
            ),
        )


if __name__ == "__main__":
    unittest.main()
