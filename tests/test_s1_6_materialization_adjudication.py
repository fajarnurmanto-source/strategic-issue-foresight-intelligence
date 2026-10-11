from __future__ import annotations

import hashlib
import json
import subprocess
import unittest
from pathlib import Path

from sifi.benchmarks import (
    CanonicalBenchmarkHarness,
    HarnessExecutionState,
    HarnessResultState,
    MATERIALIZED_EXECUTORS,
    MATERIALIZED_EXECUTOR_SPECS,
)


ROOT = Path(
    __file__
).resolve().parents[1]

MATERIALIZATION_HEAD = (
    "b322db1108e174567b2991cf4e53154f30ec3531"
)

CASE_IDS = (
    "CB01",
    "CB07",
    "CB20",
)

BASE = (
    ROOT
    / "04_IMPLEMENTATION/S1_6D/"
    "materialized_cases"
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


def adjudication(
    case_id: str,
) -> dict:
    return json.loads(
        (
            BASE
            / f"{case_id}.adjudication.json"
        ).read_text(
            encoding="utf-8"
        )
    )


class MaterializationAdjudicationTests(
    unittest.TestCase
):
    def test_001_exact_adjudicated_case_set(self):
        actual = {
            path.name.split(
                "."
            )[0]
            for path in BASE.glob(
                "*.adjudication.json"
            )
        }

        self.assertEqual(
            actual,
            set(
                CASE_IDS
            ),
        )

    def test_002_adjudication_binds_immutable_materialization(self):
        module_sha = sha256(
            ROOT / MODULE_REL
        )

        for case_id in CASE_IDS:
            record = adjudication(
                case_id
            )

            manifest_path = (
                ROOT
                / record[
                    "materialization_manifest_path"
                ]
            )

            fixture_path = (
                ROOT
                / record[
                    "fixture_path"
                ]
            )

            self.assertEqual(
                record[
                    "materialization_commit"
                ],
                MATERIALIZATION_HEAD,
            )

            self.assertEqual(
                record[
                    "materialization_manifest_sha256"
                ],
                sha256(
                    manifest_path
                ),
            )

            self.assertEqual(
                record[
                    "fixture_sha256"
                ],
                sha256(
                    fixture_path
                ),
            )

            self.assertEqual(
                record[
                    "executor_module_sha256"
                ],
                module_sha,
            )

            committed_manifest = (
                subprocess.check_output(
                    [
                        "git",
                        "show",
                        (
                            MATERIALIZATION_HEAD
                            + ":"
                            + record[
                                "materialization_manifest_path"
                            ]
                        ),
                    ]
                )
            )

            self.assertEqual(
                committed_manifest,
                manifest_path.read_bytes(),
            )

    def test_003_original_manifests_remain_not_adjudicated(self):
        for case_id in CASE_IDS:
            manifest = json.loads(
                (
                    BASE
                    / (
                        f"{case_id}."
                        "materialization.json"
                    )
                ).read_text(
                    encoding="utf-8"
                )
            )

            self.assertEqual(
                manifest[
                    "materialization_state"
                ],
                "MATERIALIZED_NOT_ADJUDICATED",
            )

            self.assertEqual(
                manifest[
                    "materialization_adjudication_state"
                ],
                "NOT_ADJUDICATED",
            )

            self.assertFalse(
                manifest[
                    "canonical_execution_authorized"
                ]
            )

    def test_004_cb01_adjudication_findings(self):
        findings = adjudication(
            "CB01"
        )[
            "semantic_findings"
        ]

        self.assertEqual(
            findings[
                "publication_count"
            ],
            20,
        )

        self.assertEqual(
            findings[
                "independent_origin_count"
            ],
            1,
        )

        self.assertTrue(
            findings[
                "publication_origin_separation_verified"
            ]
        )

        self.assertFalse(
            findings[
                "same_origin_repetition_counted_as_independent_corroboration"
            ]
        )

    def test_005_cb07_adjudication_findings(self):
        findings = adjudication(
            "CB07"
        )[
            "semantic_findings"
        ]

        self.assertEqual(
            findings[
                "claim_type"
            ],
            "CT-02",
        )

        self.assertTrue(
            findings[
                "attribution_preserved"
            ]
        )

        self.assertFalse(
            findings[
                "ct03_event_occurrence_manufactured"
            ]
        )

        self.assertFalse(
            findings[
                "ct04_empirical_condition_manufactured"
            ]
        )

        self.assertTrue(
            findings[
                "event_construction_from_allegation_alone_rejected"
            ]
        )

    def test_006_cb20_adjudication_findings(self):
        findings = adjudication(
            "CB20"
        )[
            "semantic_findings"
        ]

        self.assertEqual(
            findings[
                "supported_claim_type"
            ],
            "CT-01",
        )

        self.assertEqual(
            findings[
                "unsupported_claim_type"
            ],
            "CT-05",
        )

        self.assertEqual(
            findings[
                "prevalence_support_count"
            ],
            0,
        )

        self.assertFalse(
            findings[
                "numeric_epistemic_threshold_introduced"
            ]
        )

        self.assertFalse(
            findings[
                "fixture_cardinality_treated_as_general_threshold"
            ]
        )

    def test_007_adjudication_never_grants_execution_authority(self):
        for case_id in CASE_IDS:
            record = adjudication(
                case_id
            )

            self.assertEqual(
                record[
                    "adjudication_state"
                ],
                "ADJUDICATED_NOT_AUTHORIZED",
            )

            self.assertEqual(
                record[
                    "decision"
                ],
                (
                    "MATERIALIZATION_CONFORMS_"
                    "TO_CANONICAL_CASE_CONTRACT"
                ),
            )

            self.assertTrue(
                record[
                    "materialization_conformance_verified"
                ]
            )

            self.assertFalse(
                record[
                    "canonical_execution_authorized"
                ]
            )

            self.assertFalse(
                record[
                    "canonical_execution_performed"
                ]
            )

            self.assertFalse(
                record[
                    "canonical_result_recorded"
                ]
            )

            self.assertTrue(
                record[
                    "authorization_required_separately"
                ]
            )

    def test_008_adjudicated_executor_still_cannot_run_without_authorization(self):
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

            self.assertFalse(
                harness
                .has_execution_authorization(
                    case_id
                )
            )

            result = harness.run_case(
                case_id
            )

            self.assertEqual(
                result.execution_state,
                HarnessExecutionState
                .NOT_READY_FOR_EXECUTION,
            )

            self.assertEqual(
                result.result_state,
                HarnessResultState.NO_RESULT,
            )


if __name__ == "__main__":
    unittest.main()
