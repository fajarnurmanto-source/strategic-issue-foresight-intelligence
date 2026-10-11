from __future__ import annotations

import tempfile
import unittest
from dataclasses import replace
from datetime import datetime, timezone
from pathlib import Path

from sifi.benchmarks import (
    BenchmarkResultRecordingError,
    BenchmarkResultRepository,
    BenchmarkResultVersionSequenceError,
    CaseExecutionRecord,
    DuplicateExecutionResultError,
    ExecutionEvidenceReference,
    HarnessExecutionState,
    HarnessResultState,
)
from sifi.persistence import SQLiteVersionStore


UTC = timezone.utc
CONTRACT_FP = "a" * 64


def dt(day: int) -> datetime:
    return datetime(
        2026,
        3,
        day,
        12,
        0,
        tzinfo=UTC,
    )


def evidence() -> ExecutionEvidenceReference:
    return ExecutionEvidenceReference(
        evidence_id="EXEC-EVIDENCE-1",
        evidence_kind="COMPONENT_TEST",
        artifact_path="tests/synthetic.txt",
        sha256="b" * 64,
        producer_executor_id="EXEC-1",
        producer_executor_version="v0.1",
    )


def execution(
    *,
    expected=True,
    forbidden=False,
    regression=True,
    rights=True,
    case_id="CB01",
) -> CaseExecutionRecord:
    return CaseExecutionRecord(
        case_id=case_id,
        execution_state=(
            HarnessExecutionState.EXECUTED
        ),
        result_state=(
            HarnessResultState.NO_RESULT
        ),
        rights_basis=(
            "SYNTHETIC_NO_EXTERNAL_RIGHTS"
        ),
        analytical_cutoff_rule=(
            "CASE_DEFINED"
        ),
        reference_methodology=(
            "COMPONENT_TEST"
        ),
        executor_id="EXEC-1",
        executor_version="v0.1",
        executor_reference=(
            "tests::synthetic-executor"
        ),
        authorization_id="AUTH-1",
        contract_fingerprint=CONTRACT_FP,
        expected_conditions_satisfied=(
            expected
        ),
        forbidden_output_observed=(
            forbidden
        ),
        regression_dependencies_satisfied=(
            regression
        ),
        rights_constraints_satisfied=(
            rights
        ),
        evidence_refs=(
            evidence(),
        ),
    )


class BenchmarkResultRecordingTests(
    unittest.TestCase
):
    def setUp(self):
        self.store = SQLiteVersionStore(
            ":memory:"
        )

        self.repo = BenchmarkResultRepository(
            self.store,
            expected_contract_fingerprint=(
                CONTRACT_FP
            ),
        )

    def tearDown(self):
        self.store.close()

    def record(
        self,
        item=None,
        *,
        version=1,
        effective_day=2,
        recorded_day=3,
    ):
        return self.repo.record(
            item or execution(),
            version=version,
            effective_at=dt(
                effective_day
            ),
            recorded_at=dt(
                recorded_day
            ),
        )

    def test_001_execution_has_no_result_before_recording(self):
        item = execution()

        self.assertEqual(
            item.execution_state,
            HarnessExecutionState.EXECUTED,
        )

        self.assertEqual(
            item.result_state,
            HarnessResultState.NO_RESULT,
        )

        self.assertIsNone(
            self.repo.latest(
                "CB01"
            )
        )

    def test_002_record_computes_pass(self):
        saved = self.record()

        self.assertEqual(
            saved.result.result_state,
            HarnessResultState.PASS,
        )

        self.assertEqual(
            saved.result.recording_state,
            "RESULT_RECORDED",
        )

    def test_003_expected_false_computes_fail(self):
        saved = self.record(
            execution(
                expected=False
            )
        )

        self.assertEqual(
            saved.result.result_state,
            HarnessResultState.FAIL,
        )

    def test_004_forbidden_true_computes_fail(self):
        saved = self.record(
            execution(
                forbidden=True
            )
        )

        self.assertEqual(
            saved.result.result_state,
            HarnessResultState.FAIL,
        )

    def test_005_regression_false_computes_fail(self):
        saved = self.record(
            execution(
                regression=False
            )
        )

        self.assertEqual(
            saved.result.result_state,
            HarnessResultState.FAIL,
        )

    def test_006_rights_false_computes_fail(self):
        saved = self.record(
            execution(
                rights=False
            )
        )

        self.assertEqual(
            saved.result.result_state,
            HarnessResultState.FAIL,
        )

    def test_007_nonexecuted_record_rejected(self):
        item = CaseExecutionRecord(
            case_id="CB01",
            execution_state=(
                HarnessExecutionState
                .NOT_READY_FOR_EXECUTION
            ),
            result_state=(
                HarnessResultState.NO_RESULT
            ),
            rights_basis="SYNTHETIC",
            analytical_cutoff_rule=(
                "CASE_DEFINED"
            ),
            reference_methodology=(
                "COMPONENT_TEST"
            ),
        )

        with self.assertRaises(
            BenchmarkResultRecordingError
        ):
            self.record(
                item
            )

    def test_008_contract_mismatch_rejected(self):
        item = replace(
            execution(),
            contract_fingerprint=(
                "c" * 64
            ),
        )

        with self.assertRaises(
            BenchmarkResultRecordingError
        ):
            self.record(
                item
            )

    def test_009_sequential_versions_preserve_history(self):
        self.record(
            execution(),
            version=1,
            effective_day=2,
            recorded_day=3,
        )

        self.record(
            execution(
                case_id="CB01",
                expected=False,
            ),
            version=2,
            effective_day=4,
            recorded_day=5,
        )

        history = self.repo.history(
            "CB01"
        )

        self.assertEqual(
            tuple(
                x.version
                for x in history
            ),
            (1, 2),
        )

        self.assertEqual(
            tuple(
                x.result.result_state
                for x in history
            ),
            (
                HarnessResultState.PASS,
                HarnessResultState.FAIL,
            ),
        )

    def test_010_duplicate_execution_fingerprint_rejected(self):
        item = execution()

        self.record(
            item
        )

        with self.assertRaises(
            DuplicateExecutionResultError
        ):
            self.record(
                item,
                version=2,
                effective_day=4,
                recorded_day=5,
            )

    def test_011_point_in_time_excludes_later_result(self):
        self.record(
            execution(),
            version=1,
            effective_day=2,
            recorded_day=3,
        )

        self.record(
            execution(
                expected=False
            ),
            version=2,
            effective_day=4,
            recorded_day=10,
        )

        at_day_5 = self.repo.as_of(
            "CB01",
            dt(5),
        )

        at_day_11 = self.repo.as_of(
            "CB01",
            dt(11),
        )

        self.assertEqual(
            at_day_5.version,
            1,
        )

        self.assertEqual(
            at_day_11.version,
            2,
        )

    def test_012_file_backed_result_survives_reopen(self):
        with tempfile.TemporaryDirectory() as td:
            db = Path(td) / "benchmark.sqlite"

            with SQLiteVersionStore(db) as store:
                repo = BenchmarkResultRepository(
                    store,
                    expected_contract_fingerprint=(
                        CONTRACT_FP
                    ),
                )

                repo.record(
                    execution(),
                    version=1,
                    effective_at=dt(2),
                    recorded_at=dt(3),
                )

            with SQLiteVersionStore(db) as store:
                repo = BenchmarkResultRepository(
                    store,
                    expected_contract_fingerprint=(
                        CONTRACT_FP
                    ),
                )

                recovered = repo.latest(
                    "CB01"
                )

                self.assertIsNotNone(
                    recovered
                )

                self.assertEqual(
                    recovered.result.result_state,
                    HarnessResultState.PASS,
                )


if __name__ == "__main__":
    unittest.main()
