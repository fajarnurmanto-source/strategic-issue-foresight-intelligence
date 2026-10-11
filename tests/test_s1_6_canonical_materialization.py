from __future__ import annotations

import csv
import hashlib
import json
import unittest
from pathlib import Path
from types import SimpleNamespace

from sifi.benchmarks import (
    CanonicalBenchmarkHarness,
    HarnessExecutionState,
    HarnessResultState,
    MATERIALIZED_CASE_IDS,
    MATERIALIZED_EXECUTORS,
    MATERIALIZED_EXECUTOR_SPECS,
)


ROOT = Path(
    __file__
).resolve().parents[1]

CASE_IDS = (
    "CB01",
    "CB07",
    "CB20",
)

MODULE_REL = (
    "src/sifi/benchmarks/"
    "materialized_cases.py"
)


def sha256(
    path: Path,
) -> str:
    return hashlib.sha256(
        path.read_bytes()
    ).hexdigest()


class CanonicalMaterializationTests(
    unittest.TestCase
):
    def test_001_materialized_case_set_is_exact(self):
        self.assertEqual(
            MATERIALIZED_CASE_IDS,
            CASE_IDS,
        )

        self.assertEqual(
            set(
                MATERIALIZED_EXECUTORS
            ),
            set(
                CASE_IDS
            ),
        )

        self.assertEqual(
            set(
                MATERIALIZED_EXECUTOR_SPECS
            ),
            set(
                CASE_IDS
            ),
        )

    def test_002_manifests_remain_unadjudicated_and_unauthorized(self):
        for case_id in CASE_IDS:
            spec = (
                MATERIALIZED_EXECUTOR_SPECS[
                    case_id
                ]
            )

            payload = json.loads(
                (
                    ROOT
                    / spec.manifest_path
                ).read_text(
                    encoding="utf-8"
                )
            )

            self.assertEqual(
                payload[
                    "materialization_state"
                ],
                "MATERIALIZED_NOT_ADJUDICATED",
            )

            self.assertEqual(
                payload[
                    "materialization_adjudication_state"
                ],
                "NOT_ADJUDICATED",
            )

            self.assertFalse(
                payload[
                    "canonical_execution_authorized"
                ]
            )

            self.assertFalse(
                payload[
                    "canonical_execution_performed"
                ]
            )

            self.assertFalse(
                payload[
                    "canonical_result_recorded"
                ]
            )

    def test_003_manifests_bind_fixture_and_executor_hashes(self):
        module_hash = sha256(
            ROOT / MODULE_REL
        )

        for case_id in CASE_IDS:
            spec = (
                MATERIALIZED_EXECUTOR_SPECS[
                    case_id
                ]
            )

            manifest = json.loads(
                (
                    ROOT
                    / spec.manifest_path
                ).read_text(
                    encoding="utf-8"
                )
            )

            self.assertEqual(
                manifest[
                    "fixture_sha256"
                ],
                sha256(
                    ROOT
                    / spec.fixture_path
                ),
            )

            self.assertEqual(
                manifest[
                    "executor_module_sha256"
                ],
                module_hash,
            )

    def test_004_cb01_component_self_check(self):
        observation = (
            MATERIALIZED_EXECUTORS[
                "CB01"
            ](
                SimpleNamespace(
                    case_id="CB01"
                )
            )
        )

        self.assertTrue(
            observation
            .expected_conditions_satisfied
        )

        self.assertFalse(
            observation
            .forbidden_output_observed
        )

        self.assertTrue(
            observation
            .regression_dependencies_satisfied
        )

        self.assertTrue(
            observation
            .rights_constraints_satisfied
        )

    def test_005_cb07_component_self_check(self):
        observation = (
            MATERIALIZED_EXECUTORS[
                "CB07"
            ](
                SimpleNamespace(
                    case_id="CB07"
                )
            )
        )

        self.assertTrue(
            observation
            .expected_conditions_satisfied
        )

        self.assertFalse(
            observation
            .forbidden_output_observed
        )

        self.assertTrue(
            observation
            .regression_dependencies_satisfied
        )

        self.assertTrue(
            observation
            .rights_constraints_satisfied
        )

        self.assertIn(
            "attribution_visible=TRUE",
            observation.notes,
        )

        self.assertIn(
            (
                "allegation_event_"
                "construction_rejected=TRUE"
            ),
            observation.notes,
        )

    def test_006_cb20_component_self_check(self):
        observation = (
            MATERIALIZED_EXECUTORS[
                "CB20"
            ](
                SimpleNamespace(
                    case_id="CB20"
                )
            )
        )

        self.assertTrue(
            observation
            .expected_conditions_satisfied
        )

        self.assertFalse(
            observation
            .forbidden_output_observed
        )

        self.assertTrue(
            observation
            .regression_dependencies_satisfied
        )

        self.assertTrue(
            observation
            .rights_constraints_satisfied
        )

        self.assertIn(
            (
                "prevalence_support_count=0"
            ),
            observation.notes,
        )

    def test_007_execution_evidence_binds_fixture_and_executor(self):
        for case_id in CASE_IDS:
            spec = (
                MATERIALIZED_EXECUTOR_SPECS[
                    case_id
                ]
            )

            observation = (
                MATERIALIZED_EXECUTORS[
                    case_id
                ](
                    SimpleNamespace(
                        case_id=case_id
                    )
                )
            )

            self.assertEqual(
                len(
                    observation.evidence_refs
                ),
                1,
            )

            ref = (
                observation
                .evidence_refs[0]
            )

            self.assertEqual(
                ref.artifact_path,
                spec.fixture_path,
            )

            self.assertEqual(
                ref.sha256,
                sha256(
                    ROOT
                    / spec.fixture_path
                ),
            )

            self.assertEqual(
                ref.producer_executor_id,
                spec.executor_id,
            )

            self.assertEqual(
                ref.producer_executor_version,
                spec.executor_version,
            )

    def test_008_wrong_case_binding_is_rejected(self):
        for case_id in CASE_IDS:
            executor = (
                MATERIALIZED_EXECUTORS[
                    case_id
                ]
            )

            with self.assertRaises(
                ValueError
            ):
                executor(
                    SimpleNamespace(
                        case_id="CB99"
                    )
                )

    def test_009_registration_without_authorization_stays_not_ready(self):
        harness = (
            CanonicalBenchmarkHarness
            .from_stage0(
                ROOT
            )
        )

        for case_id in CASE_IDS:
            spec = (
                MATERIALIZED_EXECUTOR_SPECS[
                    case_id
                ]
            )

            harness.register_executor(
                case_id,
                MATERIALIZED_EXECUTORS[
                    case_id
                ],
                executor_id=(
                    spec.executor_id
                ),
                executor_version=(
                    spec.executor_version
                ),
                executor_reference=(
                    spec.executor_reference
                ),
            )

            self.assertTrue(
                harness.has_executor(
                    case_id
                )
            )

            self.assertFalse(
                harness
                .has_execution_authorization(
                    case_id
                )
            )

            record = harness.run_case(
                case_id
            )

            self.assertEqual(
                record.execution_state,
                HarnessExecutionState
                .NOT_READY_FOR_EXECUTION,
            )

            self.assertEqual(
                record.result_state,
                HarnessResultState.NO_RESULT,
            )

    def test_010_s1_6c_authority_remains_false(self):
        matrix = (
            ROOT
            / "04_IMPLEMENTATION/S1_6C/"
            "S1_6C1_CASE_EXECUTABILITY_"
            "ADJUDICATION_MATRIX_v0_1.csv"
        )

        with matrix.open(
            encoding="utf-8",
            newline="",
        ) as f:
            rows = {
                row["case_id"]: row
                for row in csv.DictReader(f)
            }

        for case_id in CASE_IDS:
            self.assertEqual(
                rows[
                    case_id
                ][
                    "adjudication_state"
                ],
                "MATERIALIZABLE_NOW",
            )

            self.assertEqual(
                rows[
                    case_id
                ][
                    "canonical_execution_authorized"
                ],
                "FALSE",
            )

    def test_011_stage0_definition_bindings_match_materialization(self):
        harness = (
            CanonicalBenchmarkHarness
            .from_stage0(
                ROOT
            )
        )

        expected = {
            "CB01": (
                "SYNTHETIC_NO_EXTERNAL_RIGHTS",
                "NOT_APPLICABLE",
                "DETERMINISTIC_SOURCE_LINEAGE",
            ),
            "CB07": (
                "SYNTHETIC_NO_EXTERNAL_RIGHTS",
                "CASE_DEFINED",
                "ADJUDICATED_CLAIM_REFERENCE",
            ),
            "CB20": (
                "SYNTHETIC_NO_EXTERNAL_RIGHTS",
                "CASE_DEFINED",
                "CLAIM_TYPE_REFERENCE",
            ),
        }

        for case_id in CASE_IDS:
            definition = (
                harness.definition(
                    case_id
                )
            )

            self.assertEqual(
                (
                    definition.rights_basis,
                    definition
                    .analytical_cutoff_rule,
                    definition
                    .reference_methodology,
                ),
                expected[
                    case_id
                ],
            )

    def test_012_materialization_module_has_no_authorization_call(self):
        source = (
            ROOT
            / MODULE_REL
        ).read_text(
            encoding="utf-8"
        )

        self.assertNotIn(
            ".authorize_execution(",
            source,
        )

        self.assertNotIn(
            "BenchmarkResultRepository",
            source,
        )


if __name__ == "__main__":
    unittest.main()
