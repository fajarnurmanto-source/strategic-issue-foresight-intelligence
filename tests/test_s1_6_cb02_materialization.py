from __future__ import annotations

import hashlib
import json
import unittest
from pathlib import Path
from types import SimpleNamespace

from sifi.benchmarks.harness import CanonicalBenchmarkHarness
from sifi.benchmarks.materialized_cases_batch2 import (
    BATCH2_EXECUTORS,
    BATCH2_EXECUTOR_SPECS,
    MATERIALIZED_BATCH2_CASE_IDS,
)
from sifi.benchmarks.model import (
    HarnessExecutionState,
    HarnessResultState,
)


ROOT = Path(__file__).resolve().parents[1]
MODULE_REL = "src/sifi/benchmarks/materialized_cases_batch2.py"


def sha256(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


class CB02MaterializationTests(unittest.TestCase):
    def test_001_batch_case_set_is_exact(self):
        self.assertEqual(MATERIALIZED_BATCH2_CASE_IDS, ("CB02",))
        self.assertEqual(set(BATCH2_EXECUTORS), {"CB02"})
        self.assertEqual(set(BATCH2_EXECUTOR_SPECS), {"CB02"})

    def test_002_component_self_check_separates_attention_and_impact(self):
        observation = BATCH2_EXECUTORS["CB02"](
            SimpleNamespace(case_id="CB02")
        )

        self.assertTrue(observation.expected_conditions_satisfied)
        self.assertFalse(observation.forbidden_output_observed)
        self.assertTrue(observation.regression_dependencies_satisfied)
        self.assertTrue(observation.rights_constraints_satisfied)
        self.assertIn("high_attention=TRUE", observation.notes)
        self.assertIn("impact_established=FALSE", observation.notes)

    def test_003_manifest_binds_fixture_and_new_executor_module(self):
        spec = BATCH2_EXECUTOR_SPECS["CB02"]
        manifest = json.loads(
            (ROOT / spec.manifest_path).read_text(encoding="utf-8")
        )

        self.assertEqual(
            manifest["fixture_sha256"],
            sha256(ROOT / spec.fixture_path),
        )
        self.assertEqual(
            manifest["executor_module_sha256"],
            sha256(ROOT / MODULE_REL),
        )
        self.assertEqual(
            manifest["materialization_state"],
            "MATERIALIZED_NOT_ADJUDICATED",
        )
        self.assertFalse(manifest["canonical_execution_authorized"])
        self.assertFalse(manifest["canonical_execution_performed"])
        self.assertFalse(manifest["canonical_result_recorded"])

    def test_004_wrong_case_binding_is_rejected(self):
        with self.assertRaises(ValueError):
            BATCH2_EXECUTORS["CB02"](
                SimpleNamespace(case_id="CB99")
            )

    def test_005_registration_without_authorization_stays_not_ready(self):
        harness = CanonicalBenchmarkHarness.from_stage0(ROOT)
        spec = BATCH2_EXECUTOR_SPECS["CB02"]

        harness.register_executor(
            "CB02",
            BATCH2_EXECUTORS["CB02"],
            executor_id=spec.executor_id,
            executor_version=spec.executor_version,
            executor_reference=spec.executor_reference,
        )

        record = harness.run_case("CB02")
        self.assertEqual(
            record.execution_state,
            HarnessExecutionState.NOT_READY_FOR_EXECUTION,
        )
        self.assertEqual(record.result_state, HarnessResultState.NO_RESULT)

    def test_006_stage0_definition_binding_is_preserved(self):
        definition = CanonicalBenchmarkHarness.from_stage0(ROOT).definition(
            "CB02"
        )

        self.assertEqual(
            (
                definition.rights_basis,
                definition.analytical_cutoff_rule,
                definition.reference_methodology,
            ),
            (
                "SYNTHETIC_NO_EXTERNAL_RIGHTS",
                "CASE_DEFINED",
                "ADJUDICATED_SYNTHETIC_REFERENCE",
            ),
        )


if __name__ == "__main__":
    unittest.main()
