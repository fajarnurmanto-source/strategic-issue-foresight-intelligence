from __future__ import annotations

import hashlib
import json
import unittest
from pathlib import Path
from types import SimpleNamespace

from sifi.benchmarks.harness import CanonicalBenchmarkHarness
from sifi.benchmarks.materialized_cases_batch6 import BATCH6_EXECUTORS, BATCH6_EXECUTOR_SPECS
from sifi.benchmarks.model import HarnessExecutionState


ROOT = Path(__file__).resolve().parents[1]


def sha256(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


class CB21MaterializationTests(unittest.TestCase):
    def test_001_component_self_check(self):
        observation = BATCH6_EXECUTORS["CB21"](SimpleNamespace(case_id="CB21"))
        self.assertTrue(observation.expected_conditions_satisfied)
        self.assertFalse(observation.forbidden_output_observed)
        self.assertTrue(observation.regression_dependencies_satisfied)
        self.assertTrue(observation.rights_constraints_satisfied)
        self.assertIn("historical_version=1", observation.notes)
        self.assertIn("latest_version=3", observation.notes)
        self.assertIn("future_information_leakage=false", observation.notes)

    def test_002_manifest_hash_bindings(self):
        spec = BATCH6_EXECUTOR_SPECS["CB21"]
        manifest = json.loads((ROOT / spec.manifest_path).read_text(encoding="utf-8"))
        self.assertEqual(manifest["fixture_sha256"], sha256(ROOT / spec.fixture_path))
        self.assertEqual(
            manifest["executor_module_sha256"], sha256(ROOT / manifest["executor_module_path"])
        )
        self.assertEqual(manifest["materialization_state"], "MATERIALIZED_NOT_ADJUDICATED")
        self.assertFalse(manifest["canonical_execution_authorized"])

    def test_003_wrong_case_binding_is_rejected(self):
        with self.assertRaises(ValueError):
            BATCH6_EXECUTORS["CB21"](SimpleNamespace(case_id="CB99"))

    def test_004_registration_does_not_authorize_execution(self):
        harness = CanonicalBenchmarkHarness.from_stage0(ROOT)
        spec = BATCH6_EXECUTOR_SPECS["CB21"]
        harness.register_executor(
            "CB21", BATCH6_EXECUTORS["CB21"], executor_id=spec.executor_id,
            executor_version=spec.executor_version, executor_reference=spec.executor_reference,
        )
        self.assertFalse(harness.has_execution_authorization("CB21"))
        self.assertEqual(
            harness.run_case("CB21").execution_state,
            HarnessExecutionState.NOT_READY_FOR_EXECUTION,
        )

    def test_005_definition_binding_is_preserved(self):
        definition = CanonicalBenchmarkHarness.from_stage0(ROOT).definition("CB21")
        self.assertEqual(
            (definition.rights_basis, definition.analytical_cutoff_rule, definition.reference_methodology),
            ("SYNTHETIC_NO_EXTERNAL_RIGHTS", "FROZEN_T_REQUIRED", "POINT_IN_TIME_REFERENCE"),
        )


if __name__ == "__main__":
    unittest.main()
