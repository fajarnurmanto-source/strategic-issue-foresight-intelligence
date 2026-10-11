from __future__ import annotations

import hashlib
from dataclasses import replace
import shutil
import tempfile
import unittest
from pathlib import Path

from sifi.benchmarks import (
    BenchmarkRegistrationError,
    CanonicalBenchmarkHarness,
    CaseObservation,
    ExecutionEvidenceReference,
    HarnessExecutionState,
    HarnessResultState,
)

from sifi.benchmarks.authorization import (
    BenchmarkAuthorizationError,
    CaseExecutionAuthorization,
)


ROOT = Path(
    __file__
).resolve().parents[1]

THIS_TEST_REL = (
    "tests/"
    "test_s1_6_canonical_benchmark_harness.py"
)


AUTH_FIXTURE_DIR_REL = (
    "tests/fixtures/"
    "s1_6_authorization"
)

MATERIALIZATION_REL = (
    AUTH_FIXTURE_DIR_REL
    + "/materialization.txt"
)

ADJUDICATION_REL = (
    AUTH_FIXTURE_DIR_REL
    + "/adjudication.txt"
)

AUTHORIZATION_REL = (
    AUTH_FIXTURE_DIR_REL
    + "/authorization.txt"
)


def sha256(
    path: Path,
) -> str:
    return hashlib.sha256(
        path.read_bytes()
    ).hexdigest()


def make_harness(
    root: Path = ROOT,
) -> CanonicalBenchmarkHarness:
    return (
        CanonicalBenchmarkHarness
        .from_stage0(root)
    )


def evidence(
    *,
    root: Path = ROOT,
    evidence_id: str = "TEST-EVIDENCE-1",
    artifact_path: str = THIS_TEST_REL,
    executor_id: str = "TEST-EXECUTOR",
    executor_version: str = "v0.1",
) -> ExecutionEvidenceReference:
    path = (
        root
        / artifact_path
    )

    return ExecutionEvidenceReference(
        evidence_id=evidence_id,
        evidence_kind=(
            "HARNESS_COMPONENT_TEST"
        ),
        artifact_path=artifact_path,
        sha256=sha256(path),
        producer_executor_id=executor_id,
        producer_executor_version=(
            executor_version
        ),
    )


def passing_observation(
    *,
    root: Path = ROOT,
    executor_id: str = "TEST-EXECUTOR",
    executor_version: str = "v0.1",
    artifact_path: str = THIS_TEST_REL,
    cutoff: str | None = None,
) -> CaseObservation:
    return CaseObservation(
        expected_conditions_satisfied=True,
        forbidden_output_observed=False,
        regression_dependencies_satisfied=True,
        rights_constraints_satisfied=True,
        evidence_refs=(
            evidence(
                root=root,
                artifact_path=artifact_path,
                executor_id=executor_id,
                executor_version=executor_version,
            ),
        ),
        analytical_cutoff=cutoff,
    )


def execution_authorization(
    harness: CanonicalBenchmarkHarness,
    case_id: str,
    *,
    executor_id: str = "TEST-EXECUTOR",
    executor_version: str = "v0.1",
    executor_reference: str = (
        "tests::synthetic-harness-executor"
    ),
) -> CaseExecutionAuthorization:
    return CaseExecutionAuthorization(
        authorization_id=(
            "TEST-AUTH-"
            + case_id
        ),
        case_id=case_id,
        executor_id=executor_id,
        executor_version=executor_version,
        executor_reference=executor_reference,
        materialization_reference=(
            MATERIALIZATION_REL
        ),
        materialization_sha256=sha256(
            ROOT / MATERIALIZATION_REL
        ),
        adjudication_reference=(
            ADJUDICATION_REL
        ),
        adjudication_sha256=sha256(
            ROOT / ADJUDICATION_REL
        ),
        authorization_reference=(
            AUTHORIZATION_REL
        ),
        authorization_sha256=sha256(
            ROOT / AUTHORIZATION_REL
        ),
        contract_fingerprint=(
            harness.contract_fingerprint
        ),
    )


def register(
    harness: CanonicalBenchmarkHarness,
    case_id: str,
    executor,
    *,
    executor_id: str = "TEST-EXECUTOR",
    executor_version: str = "v0.1",
    executor_reference: str = (
        "tests::synthetic-harness-executor"
    ),
    authorize_execution: bool = True,
) -> None:
    harness.register_executor(
        case_id,
        executor,
        executor_id=executor_id,
        executor_version=executor_version,
        executor_reference=executor_reference,
    )

    if authorize_execution:
        harness.authorize_execution(
            execution_authorization(
                harness,
                case_id,
                executor_id=executor_id,
                executor_version=(
                    executor_version
                ),
                executor_reference=(
                    executor_reference
                ),
            )
        )


def temporary_stage0_root() -> tempfile.TemporaryDirectory:
    return tempfile.TemporaryDirectory()


def populate_temp_root(
    root: Path,
) -> None:
    for rel in (
        Path("02_BENCHMARKS/S0_6A"),
        Path("02_BENCHMARKS/S0_6B"),
    ):
        source = ROOT / rel

        if source.exists():
            shutil.copytree(
                source,
                root / rel,
            )

    source_foundation = (
        ROOT
        / "01_STAGE0_FOUNDATION"
    )

    if source_foundation.exists():
        shutil.copytree(
            source_foundation,
            root
            / "01_STAGE0_FOUNDATION",
        )

    project_state = (
        ROOT
        / "00_CONTROL"
        / "PROJECT_STATE.md"
    )

    if project_state.is_file():
        target = (
            root
            / "00_CONTROL"
            / "PROJECT_STATE.md"
        )

        target.parent.mkdir(
            parents=True,
            exist_ok=True,
        )

        shutil.copy2(
            project_state,
            target,
        )

    auth_source = (
        ROOT
        / AUTH_FIXTURE_DIR_REL
    )

    auth_target = (
        root
        / AUTH_FIXTURE_DIR_REL
    )

    auth_target.parent.mkdir(
        parents=True,
        exist_ok=True,
    )

    shutil.copytree(
        auth_source,
        auth_target,
    )

    evidence_path = (
        root
        / "execution-evidence.txt"
    )

    evidence_path.write_text(
        "synthetic component-test evidence\n",
        encoding="utf-8",
    )


class HarnessContractTests(
    unittest.TestCase
):

    def test_001_loads_exactly_24_cases(self):
        harness = make_harness()

        self.assertEqual(
            len(harness.definitions),
            24,
        )

    def test_002_case_ids_are_exact(self):
        harness = make_harness()

        self.assertEqual(
            {
                d.case_id
                for d in harness.definitions
            },
            {
                f"CB{i:02d}"
                for i in range(1, 25)
            },
        )

    def test_003_protocol_only_is_exact(self):
        harness = make_harness()

        self.assertEqual(
            {
                d.case_id
                for d in harness.definitions
                if d.protocol_only
            },
            {
                "CB22",
                "CB23",
            },
        )

    def test_004_frozen_states_remain_unexecuted(self):
        harness = make_harness()

        for definition in harness.definitions:
            self.assertEqual(
                definition
                .registry_execution_state,
                "NOT_EXECUTED",
            )
            self.assertEqual(
                definition
                .registry_result_state,
                "NO_RESULT",
            )
            self.assertEqual(
                definition
                .control_execution_state,
                "NOT_EXECUTED",
            )
            self.assertEqual(
                definition
                .control_result_state,
                "NO_RESULT",
            )

    def test_005_unknown_registration_rejected(self):
        harness = make_harness()

        with self.assertRaises(
            KeyError
        ):
            register(
                harness,
                "CB99",
                lambda d: passing_observation(),
            )

    def test_006_protocol_registration_rejected(self):
        harness = make_harness()

        with self.assertRaises(
            BenchmarkRegistrationError
        ):
            register(
                harness,
                "CB22",
                lambda d: passing_observation(
                    cutoff=(
                        "2026-01-01T00:00:00Z"
                    )
                ),
            )

    def test_007_missing_executor_is_not_ready(self):
        harness = make_harness()

        record = harness.run_case(
            "CB01"
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

    def test_008_protocol_case_has_no_result(self):
        harness = make_harness()

        record = harness.run_case(
            "CB22"
        )

        self.assertEqual(
            record.execution_state,
            HarnessExecutionState
            .PROTOCOL_ONLY,
        )

        self.assertEqual(
            record.result_state,
            HarnessResultState.NO_RESULT,
        )

    def test_009_executed_case_can_pass(self):
        harness = make_harness()

        register(
            harness,
            "CB01",
            lambda d: passing_observation(),
        )

        record = harness.run_case(
            "CB01"
        )

        self.assertEqual(
            record.execution_state,
            HarnessExecutionState.EXECUTED,
        )

        self.assertEqual(
            record.result_state,
            HarnessResultState.PASS,
        )

    def test_010_expected_failure_causes_fail(self):
        harness = make_harness()

        def executor(definition):
            return CaseObservation(
                expected_conditions_satisfied=False,
                forbidden_output_observed=False,
                regression_dependencies_satisfied=True,
                rights_constraints_satisfied=True,
                evidence_refs=(
                    evidence(),
                ),
            )

        register(
            harness,
            "CB01",
            executor,
        )

        record = harness.run_case(
            "CB01"
        )

        self.assertEqual(
            record.result_state,
            HarnessResultState.FAIL,
        )

    def test_011_forbidden_output_causes_fail(self):
        harness = make_harness()

        def executor(definition):
            return CaseObservation(
                expected_conditions_satisfied=True,
                forbidden_output_observed=True,
                regression_dependencies_satisfied=True,
                rights_constraints_satisfied=True,
                evidence_refs=(
                    evidence(),
                ),
            )

        register(
            harness,
            "CB01",
            executor,
        )

        record = harness.run_case(
            "CB01"
        )

        self.assertEqual(
            record.result_state,
            HarnessResultState.FAIL,
        )

    def test_012_regression_failure_causes_fail(self):
        harness = make_harness()

        def executor(definition):
            return CaseObservation(
                expected_conditions_satisfied=True,
                forbidden_output_observed=False,
                regression_dependencies_satisfied=False,
                rights_constraints_satisfied=True,
                evidence_refs=(
                    evidence(),
                ),
            )

        register(
            harness,
            "CB01",
            executor,
        )

        record = harness.run_case(
            "CB01"
        )

        self.assertEqual(
            record.result_state,
            HarnessResultState.FAIL,
        )

    def test_013_rights_failure_causes_fail(self):
        harness = make_harness()

        def executor(definition):
            return CaseObservation(
                expected_conditions_satisfied=True,
                forbidden_output_observed=False,
                regression_dependencies_satisfied=True,
                rights_constraints_satisfied=False,
                evidence_refs=(
                    evidence(),
                ),
            )

        register(
            harness,
            "CB01",
            executor,
        )

        record = harness.run_case(
            "CB01"
        )

        self.assertEqual(
            record.result_state,
            HarnessResultState.FAIL,
        )

    def test_014_executor_exception_is_no_result(self):
        harness = make_harness()

        def broken(definition):
            raise RuntimeError(
                "synthetic harness failure"
            )

        register(
            harness,
            "CB01",
            broken,
        )

        record = harness.run_case(
            "CB01"
        )

        self.assertEqual(
            record.execution_state,
            HarnessExecutionState
            .EXECUTION_ERROR,
        )

        self.assertEqual(
            record.result_state,
            HarnessResultState.NO_RESULT,
        )

        self.assertEqual(
            record.error_type,
            "RuntimeError",
        )

    def test_015_frozen_cutoff_is_required(self):
        harness = make_harness()

        register(
            harness,
            "CB21",
            lambda d: passing_observation(
                cutoff=None
            ),
        )

        record = harness.run_case(
            "CB21"
        )

        self.assertEqual(
            record.execution_state,
            HarnessExecutionState
            .EXECUTION_ERROR,
        )

        self.assertEqual(
            record.result_state,
            HarnessResultState.NO_RESULT,
        )

        self.assertEqual(
            record.error_type,
            "MISSING_ANALYTICAL_CUTOFF",
        )

    def test_016_run_all_preserves_partition(self):
        harness = make_harness()

        register(
            harness,
            "CB01",
            lambda d: passing_observation(),
        )

        records = harness.run_all()

        executed = [
            r
            for r in records
            if (
                r.execution_state
                == HarnessExecutionState.EXECUTED
            )
        ]

        blocked = [
            r
            for r in records
            if (
                r.execution_state
                == HarnessExecutionState
                .NOT_READY_FOR_EXECUTION
            )
        ]

        protocol = [
            r
            for r in records
            if (
                r.execution_state
                == HarnessExecutionState
                .PROTOCOL_ONLY
            )
        ]

        self.assertEqual(
            len(records),
            24,
        )
        self.assertEqual(
            len(executed),
            1,
        )
        self.assertEqual(
            executed[0].case_id,
            "CB01",
        )
        self.assertEqual(
            len(blocked),
            21,
        )
        self.assertEqual(
            len(protocol),
            2,
        )

    def test_017_loading_does_not_mutate_stage0(self):
        paths = (
            ROOT
            / "02_BENCHMARKS"
            / "S0_6A"
            / "S0_6A2_CANONICAL_BENCHMARK_CASE_REGISTRY_v0_1.csv",
            ROOT
            / "02_BENCHMARKS"
            / "S0_6A"
            / "S0_6A4_BENCHMARK_CASE_CONTROL_MATRIX_v0_1.csv",
        )

        before = {
            p: sha256(p)
            for p in paths
        }

        harness = make_harness()

        harness.run_all()

        after = {
            p: sha256(p)
            for p in paths
        }

        self.assertEqual(
            before,
            after,
        )

    def test_018_execution_record_has_executor_identity(self):
        harness = make_harness()

        register(
            harness,
            "CB01",
            lambda d: passing_observation(),
            executor_id="EXEC-CB01",
            executor_version="1.2.3",
            executor_reference=(
                "tests::cb01"
            ),
        )

        record = harness.run_case(
            "CB01"
        )

        self.assertEqual(
            record.executor_id,
            "EXEC-CB01",
        )
        self.assertEqual(
            record.executor_version,
            "1.2.3",
        )
        self.assertEqual(
            record.executor_reference,
            "tests::cb01",
        )

    def test_019_arbitrary_string_evidence_rejected(self):
        with self.assertRaises(
            ValueError
        ):
            CaseObservation(
                expected_conditions_satisfied=True,
                forbidden_output_observed=False,
                regression_dependencies_satisfied=True,
                rights_constraints_satisfied=True,
                evidence_refs=(
                    "ARBITRARY-STRING",
                ),
            )

    def test_020_missing_evidence_artifact_no_result(self):
        harness = make_harness()

        def executor(definition):
            return CaseObservation(
                expected_conditions_satisfied=True,
                forbidden_output_observed=False,
                regression_dependencies_satisfied=True,
                rights_constraints_satisfied=True,
                evidence_refs=(
                    ExecutionEvidenceReference(
                        evidence_id="MISSING",
                        evidence_kind="TEST",
                        artifact_path=(
                            "does/not/exist.txt"
                        ),
                        sha256="0" * 64,
                        producer_executor_id=(
                            "TEST-EXECUTOR"
                        ),
                        producer_executor_version=(
                            "v0.1"
                        ),
                    ),
                ),
            )

        register(
            harness,
            "CB01",
            executor,
        )

        record = harness.run_case(
            "CB01"
        )

        self.assertEqual(
            record.execution_state,
            HarnessExecutionState
            .EXECUTION_ERROR,
        )

        self.assertEqual(
            record.result_state,
            HarnessResultState.NO_RESULT,
        )

        self.assertEqual(
            record.error_type,
            "EVIDENCE_REFERENCE_VALIDATION_FAILED",
        )

    def test_021_evidence_digest_mismatch_no_result(self):
        harness = make_harness()

        def executor(definition):
            return CaseObservation(
                expected_conditions_satisfied=True,
                forbidden_output_observed=False,
                regression_dependencies_satisfied=True,
                rights_constraints_satisfied=True,
                evidence_refs=(
                    ExecutionEvidenceReference(
                        evidence_id="BAD-HASH",
                        evidence_kind="TEST",
                        artifact_path=(
                            THIS_TEST_REL
                        ),
                        sha256="0" * 64,
                        producer_executor_id=(
                            "TEST-EXECUTOR"
                        ),
                        producer_executor_version=(
                            "v0.1"
                        ),
                    ),
                ),
            )

        register(
            harness,
            "CB01",
            executor,
        )

        record = harness.run_case(
            "CB01"
        )

        self.assertEqual(
            record.execution_state,
            HarnessExecutionState
            .EXECUTION_ERROR,
        )

        self.assertEqual(
            record.result_state,
            HarnessResultState.NO_RESULT,
        )

    def test_022_evidence_producer_mismatch_no_result(self):
        harness = make_harness()

        def executor(definition):
            return CaseObservation(
                expected_conditions_satisfied=True,
                forbidden_output_observed=False,
                regression_dependencies_satisfied=True,
                rights_constraints_satisfied=True,
                evidence_refs=(
                    evidence(
                        executor_id=(
                            "DIFFERENT-EXECUTOR"
                        ),
                    ),
                ),
            )

        register(
            harness,
            "CB01",
            executor,
        )

        record = harness.run_case(
            "CB01"
        )

        self.assertEqual(
            record.execution_state,
            HarnessExecutionState
            .EXECUTION_ERROR,
        )

        self.assertEqual(
            record.result_state,
            HarnessResultState.NO_RESULT,
        )

    def test_023_registry_mutation_cannot_pass(self):
        with temporary_stage0_root() as td:
            root = Path(td)

            populate_temp_root(
                root
            )

            harness = make_harness(
                root
            )

            registry = (
                root
                / "02_BENCHMARKS"
                / "S0_6A"
                / "S0_6A2_CANONICAL_BENCHMARK_CASE_REGISTRY_v0_1.csv"
            )

            def executor(definition):
                with registry.open(
                    "a",
                    encoding="utf-8",
                ) as handle:
                    handle.write(
                        "\n# mutation\n"
                    )

                return passing_observation(
                    root=root,
                    artifact_path=(
                        "execution-evidence.txt"
                    ),
                )

            register(
                harness,
                "CB01",
                executor,
            )

            record = harness.run_case(
                "CB01"
            )

            self.assertEqual(
                record.execution_state,
                HarnessExecutionState
                .EXECUTION_ERROR,
            )

            self.assertEqual(
                record.result_state,
                HarnessResultState.NO_RESULT,
            )

            self.assertEqual(
                record.error_type,
                "PROTECTED_ARTIFACT_MUTATION",
            )

    def test_024_case_spec_mutation_cannot_pass(self):
        with temporary_stage0_root() as td:
            root = Path(td)

            populate_temp_root(
                root
            )

            harness = make_harness(
                root
            )

            spec = (
                root
                / "02_BENCHMARKS"
                / "S0_6A"
                / "S0_6A3_CANONICAL_BENCHMARK_CASE_SPECIFICATIONS_v0_1.md"
            )

            def executor(definition):
                with spec.open(
                    "a",
                    encoding="utf-8",
                ) as handle:
                    handle.write(
                        "\nmutation\n"
                    )

                return passing_observation(
                    root=root,
                    artifact_path=(
                        "execution-evidence.txt"
                    ),
                )

            register(
                harness,
                "CB01",
                executor,
            )

            record = harness.run_case(
                "CB01"
            )

            self.assertEqual(
                record.execution_state,
                HarnessExecutionState
                .EXECUTION_ERROR,
            )

            self.assertEqual(
                record.result_state,
                HarnessResultState.NO_RESULT,
            )

            self.assertEqual(
                record.error_type,
                "PROTECTED_ARTIFACT_MUTATION",
            )

    def test_025_pre_execution_integrity_mismatch_blocks(self):
        with temporary_stage0_root() as td:
            root = Path(td)

            populate_temp_root(
                root
            )

            harness = make_harness(
                root
            )

            control = (
                root
                / "02_BENCHMARKS"
                / "S0_6A"
                / "S0_6A4_BENCHMARK_CASE_CONTROL_MATRIX_v0_1.csv"
            )

            register(
                harness,
                "CB01",
                lambda d: passing_observation(
                    root=root,
                    artifact_path=(
                        "execution-evidence.txt"
                    ),
                ),
            )

            with control.open(
                "a",
                encoding="utf-8",
            ) as handle:
                handle.write(
                    "\n# external mutation\n"
                )

            record = harness.run_case(
                "CB01"
            )

            self.assertEqual(
                record.execution_state,
                HarnessExecutionState
                .EXECUTION_ERROR,
            )

            self.assertEqual(
                record.result_state,
                HarnessResultState.NO_RESULT,
            )

            self.assertEqual(
                record.error_type,
                "PROTECTED_ARTIFACT_"
                "INTEGRITY_MISMATCH",
            )

    def test_026_registration_alone_cannot_execute(self):
        harness = make_harness()

        called = {
            "value": False
        }

        def executor(definition):
            called["value"] = True
            return passing_observation()

        register(
            harness,
            "CB01",
            executor,
            authorize_execution=False,
        )

        self.assertTrue(
            harness.has_executor(
                "CB01"
            )
        )

        self.assertFalse(
            harness.has_execution_authorization(
                "CB01"
            )
        )

        record = harness.run_case(
            "CB01"
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

        self.assertFalse(
            called["value"]
        )

    def test_027_authorization_requires_executor(self):
        harness = make_harness()

        with self.assertRaises(
            BenchmarkAuthorizationError
        ):
            harness.authorize_execution(
                execution_authorization(
                    harness,
                    "CB01",
                )
            )

    def test_028_protocol_authorization_rejected(self):
        harness = make_harness()

        with self.assertRaises(
            BenchmarkAuthorizationError
        ):
            harness.authorize_execution(
                execution_authorization(
                    harness,
                    "CB22",
                )
            )

    def test_029_bad_materialization_hash_rejected(self):
        harness = make_harness()

        register(
            harness,
            "CB01",
            lambda d: passing_observation(),
            authorize_execution=False,
        )

        bad = replace(
            execution_authorization(
                harness,
                "CB01",
            ),
            materialization_sha256=(
                "0" * 64
            ),
        )

        with self.assertRaises(
            BenchmarkAuthorizationError
        ):
            harness.authorize_execution(
                bad
            )

    def test_030_bad_contract_fingerprint_rejected(self):
        harness = make_harness()

        register(
            harness,
            "CB01",
            lambda d: passing_observation(),
            authorize_execution=False,
        )

        bad = replace(
            execution_authorization(
                harness,
                "CB01",
            ),
            contract_fingerprint=(
                "0" * 64
            ),
        )

        with self.assertRaises(
            BenchmarkAuthorizationError
        ):
            harness.authorize_execution(
                bad
            )

    def test_031_valid_authorization_is_explicit(self):
        harness = make_harness()

        register(
            harness,
            "CB01",
            lambda d: passing_observation(),
        )

        self.assertTrue(
            harness.has_executor(
                "CB01"
            )
        )

        self.assertTrue(
            harness.has_execution_authorization(
                "CB01"
            )
        )

    def test_032_authorization_artifact_mutation_blocks_execution(self):
        with temporary_stage0_root() as td:
            root = Path(td)

            populate_temp_root(
                root
            )

            harness = make_harness(
                root
            )

            called = {
                "value": False
            }

            def executor(definition):
                called["value"] = True

                return passing_observation(
                    root=root,
                    artifact_path=(
                        "execution-evidence.txt"
                    ),
                )

            register(
                harness,
                "CB01",
                executor,
            )

            authorization_path = (
                root
                / AUTHORIZATION_REL
            )

            with authorization_path.open(
                "a",
                encoding="utf-8",
            ) as handle:
                handle.write(
                    "mutation\n"
                )

            record = harness.run_case(
                "CB01"
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

            self.assertEqual(
                record.error_type,
                "EXECUTION_AUTHORIZATION_INVALID",
            )

            self.assertFalse(
                called["value"]
            )

    def test_033_duplicate_authorization_rejected(self):
        harness = make_harness()

        register(
            harness,
            "CB01",
            lambda d: passing_observation(),
        )

        with self.assertRaises(
            BenchmarkAuthorizationError
        ):
            harness.authorize_execution(
                execution_authorization(
                    harness,
                    "CB01",
                )
            )


if __name__ == "__main__":
    unittest.main()
