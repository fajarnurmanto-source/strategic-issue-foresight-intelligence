from __future__ import annotations

import csv
import hashlib
from collections.abc import Callable
from dataclasses import dataclass
from pathlib import Path

from .model import (
    CanonicalCaseDefinition,
    CaseExecutionRecord,
    CaseObservation,
    ExecutionEvidenceReference,
    HarnessExecutionState,
    HarnessResultState,
)


CaseExecutor = Callable[
    [CanonicalCaseDefinition],
    CaseObservation,
]


class BenchmarkContractError(RuntimeError):
    pass


class BenchmarkRegistrationError(RuntimeError):
    pass


@dataclass(frozen=True, slots=True)
class _ExecutorRegistration:
    executor: CaseExecutor
    executor_id: str
    executor_version: str
    executor_reference: str


class CanonicalBenchmarkHarness:
    def __init__(
        self,
        definitions: tuple[
            CanonicalCaseDefinition,
            ...
        ],
        *,
        repo_root: str | Path,
    ) -> None:
        if len(definitions) != 24:
            raise BenchmarkContractError(
                "canonical harness requires "
                "exactly 24 cases"
            )

        by_id = {
            definition.case_id: definition
            for definition in definitions
        }

        if len(by_id) != 24:
            raise BenchmarkContractError(
                "duplicate canonical case id"
            )

        expected_ids = {
            f"CB{i:02d}"
            for i in range(1, 25)
        }

        if set(by_id) != expected_ids:
            raise BenchmarkContractError(
                "canonical case IDs differ "
                "from CB01-CB24"
            )

        protocol_ids = {
            definition.case_id
            for definition in definitions
            if definition.protocol_only
        }

        if protocol_ids != {
            "CB22",
            "CB23",
        }:
            raise BenchmarkContractError(
                "protocol-only set must be "
                "exactly CB22 and CB23"
            )

        for definition in definitions:
            if (
                definition.registry_execution_state
                != "NOT_EXECUTED"
            ):
                raise BenchmarkContractError(
                    f"{definition.case_id}: frozen "
                    "registry execution state changed"
                )

            if (
                definition.registry_result_state
                != "NO_RESULT"
            ):
                raise BenchmarkContractError(
                    f"{definition.case_id}: frozen "
                    "registry result state changed"
                )

            if (
                definition.control_execution_state
                != "NOT_EXECUTED"
            ):
                raise BenchmarkContractError(
                    f"{definition.case_id}: frozen "
                    "control execution state changed"
                )

            if (
                definition.control_result_state
                != "NO_RESULT"
            ):
                raise BenchmarkContractError(
                    f"{definition.case_id}: frozen "
                    "control result state changed"
                )

        self._repo_root = Path(
            repo_root
        ).resolve()

        if not self._repo_root.is_dir():
            raise BenchmarkContractError(
                "repo_root must exist"
            )

        self._definitions = tuple(
            sorted(
                definitions,
                key=lambda x: x.case_id,
            )
        )

        self._by_id = {
            definition.case_id: definition
            for definition in self._definitions
        }

        self._executors: dict[
            str,
            _ExecutorRegistration,
        ] = {}

        self._protected_baseline = (
            self._fingerprint_protected_artifacts()
        )

    @classmethod
    def from_stage0(
        cls,
        repo_root: str | Path,
    ) -> "CanonicalBenchmarkHarness":
        root = Path(
            repo_root
        ).resolve()

        registry_path = (
            root
            / "02_BENCHMARKS"
            / "S0_6A"
            / "S0_6A2_CANONICAL_BENCHMARK_CASE_REGISTRY_v0_1.csv"
        )

        control_path = (
            root
            / "02_BENCHMARKS"
            / "S0_6A"
            / "S0_6A4_BENCHMARK_CASE_CONTROL_MATRIX_v0_1.csv"
        )

        registry = _read_csv(
            registry_path
        )

        control = _read_csv(
            control_path
        )

        required_registry = {
            "case_id",
            "title",
            "legacy_refs",
            "domain",
            "materialization_state",
            "execution_state",
            "result_state",
        }

        required_control = {
            "case_id",
            "rights_basis",
            "analytical_cutoff_rule",
            "reference_methodology",
            "benchmark_input_reference",
            "expected_output_reference",
            "forbidden_output_reference",
            "acceptance_reference",
            "failure_condition",
            "contract_coverage_reference",
            "regression_dependency_reference",
            "materialization_state",
            "execution_state",
            "result_state",
        }

        _validate_fields(
            registry_path,
            registry,
            required_registry,
        )

        _validate_fields(
            control_path,
            control,
            required_control,
        )

        registry_by_id = _index_rows(
            registry_path,
            registry,
        )

        control_by_id = _index_rows(
            control_path,
            control,
        )

        if (
            set(registry_by_id)
            != set(control_by_id)
        ):
            raise BenchmarkContractError(
                "registry/control case IDs differ"
            )

        definitions = []

        for case_id in sorted(
            registry_by_id
        ):
            reg = registry_by_id[case_id]
            ctl = control_by_id[case_id]

            definitions.append(
                CanonicalCaseDefinition(
                    case_id=case_id,
                    title=reg["title"],
                    legacy_refs=reg["legacy_refs"],
                    domain=reg["domain"],
                    registry_materialization_state=(
                        reg["materialization_state"]
                    ),
                    registry_execution_state=(
                        reg["execution_state"]
                    ),
                    registry_result_state=(
                        reg["result_state"]
                    ),
                    rights_basis=(
                        ctl["rights_basis"]
                    ),
                    analytical_cutoff_rule=(
                        ctl["analytical_cutoff_rule"]
                    ),
                    reference_methodology=(
                        ctl["reference_methodology"]
                    ),
                    benchmark_input_reference=(
                        ctl["benchmark_input_reference"]
                    ),
                    expected_output_reference=(
                        ctl["expected_output_reference"]
                    ),
                    forbidden_output_reference=(
                        ctl["forbidden_output_reference"]
                    ),
                    acceptance_reference=(
                        ctl["acceptance_reference"]
                    ),
                    failure_condition=(
                        ctl["failure_condition"]
                    ),
                    contract_coverage_reference=(
                        ctl["contract_coverage_reference"]
                    ),
                    regression_dependency_reference=(
                        ctl["regression_dependency_reference"]
                    ),
                    control_materialization_state=(
                        ctl["materialization_state"]
                    ),
                    control_execution_state=(
                        ctl["execution_state"]
                    ),
                    control_result_state=(
                        ctl["result_state"]
                    ),
                )
            )

        return cls(
            tuple(definitions),
            repo_root=root,
        )

    @property
    def definitions(
        self,
    ) -> tuple[
        CanonicalCaseDefinition,
        ...
    ]:
        return self._definitions

    def definition(
        self,
        case_id: str,
    ) -> CanonicalCaseDefinition:
        try:
            return self._by_id[case_id]
        except KeyError as exc:
            raise KeyError(
                f"unknown canonical case: {case_id}"
            ) from exc

    def register_executor(
        self,
        case_id: str,
        executor: CaseExecutor,
        *,
        executor_id: str,
        executor_version: str,
        executor_reference: str,
    ) -> None:
        definition = self.definition(
            case_id
        )

        if definition.protocol_only:
            raise BenchmarkRegistrationError(
                f"{case_id} is PROTOCOL_ONLY"
            )

        if case_id in self._executors:
            raise BenchmarkRegistrationError(
                f"{case_id} executor already registered"
            )

        if not callable(executor):
            raise BenchmarkRegistrationError(
                "executor must be callable"
            )

        for name, value in (
            (
                "executor_id",
                executor_id,
            ),
            (
                "executor_version",
                executor_version,
            ),
            (
                "executor_reference",
                executor_reference,
            ),
        ):
            if (
                not isinstance(value, str)
                or not value.strip()
            ):
                raise BenchmarkRegistrationError(
                    f"{name} must be non-empty"
                )

        self._executors[case_id] = (
            _ExecutorRegistration(
                executor=executor,
                executor_id=executor_id,
                executor_version=executor_version,
                executor_reference=executor_reference,
            )
        )

    def has_executor(
        self,
        case_id: str,
    ) -> bool:
        self.definition(case_id)
        return case_id in self._executors

    def run_case(
        self,
        case_id: str,
    ) -> CaseExecutionRecord:
        definition = self.definition(
            case_id
        )

        if definition.protocol_only:
            return CaseExecutionRecord(
                case_id=case_id,
                execution_state=(
                    HarnessExecutionState.PROTOCOL_ONLY
                ),
                result_state=(
                    HarnessResultState.NO_RESULT
                ),
                rights_basis=(
                    definition.rights_basis
                ),
                analytical_cutoff_rule=(
                    definition.analytical_cutoff_rule
                ),
                reference_methodology=(
                    definition.reference_methodology
                ),
                blocking_reason=(
                    "canonical case is protocol-only "
                    "pending required real-user or "
                    "comparative evidence"
                ),
            )

        registration = self._executors.get(
            case_id
        )

        if registration is None:
            return CaseExecutionRecord(
                case_id=case_id,
                execution_state=(
                    HarnessExecutionState
                    .BLOCKED_BY_MISSING_CAPABILITY
                ),
                result_state=(
                    HarnessResultState.NO_RESULT
                ),
                rights_basis=(
                    definition.rights_basis
                ),
                analytical_cutoff_rule=(
                    definition.analytical_cutoff_rule
                ),
                reference_methodology=(
                    definition.reference_methodology
                ),
                blocking_reason=(
                    "no canonical executor registered"
                ),
            )

        integrity_error = (
            self._protected_integrity_error()
        )

        if integrity_error is not None:
            return self._execution_error(
                definition,
                registration,
                error_type=(
                    "PROTECTED_ARTIFACT_"
                    "INTEGRITY_MISMATCH"
                ),
                blocking_reason=(
                    "protected canonical artifacts "
                    "differ from harness baseline "
                    "before execution"
                ),
                notes=(
                    integrity_error,
                ),
            )

        observation = None
        executor_exception = None

        try:
            observation = (
                registration.executor(
                    definition
                )
            )
        except Exception as exc:
            executor_exception = exc

        post_error = (
            self._protected_integrity_error()
        )

        if post_error is not None:
            notes = [
                post_error,
            ]

            if (
                executor_exception is not None
                and str(executor_exception)
            ):
                notes.append(
                    "executor_exception="
                    + str(executor_exception)
                )

            return self._execution_error(
                definition,
                registration,
                error_type=(
                    "PROTECTED_ARTIFACT_MUTATION"
                ),
                blocking_reason=(
                    "executor changed protected "
                    "canonical artifacts"
                ),
                notes=tuple(notes),
            )

        if executor_exception is not None:
            return self._execution_error(
                definition,
                registration,
                error_type=(
                    type(executor_exception).__name__
                ),
                blocking_reason=(
                    "executor raised an exception"
                ),
                notes=(
                    str(executor_exception),
                )
                if str(executor_exception)
                else (),
            )

        if not isinstance(
            observation,
            CaseObservation,
        ):
            return self._execution_error(
                definition,
                registration,
                error_type=(
                    "INVALID_OBSERVATION"
                ),
                blocking_reason=(
                    "executor returned invalid "
                    "observation type"
                ),
            )

        evidence_error = (
            self._execution_evidence_error(
                observation.evidence_refs,
                registration,
            )
        )

        if evidence_error is not None:
            return self._execution_error(
                definition,
                registration,
                error_type=(
                    "EVIDENCE_REFERENCE_"
                    "VALIDATION_FAILED"
                ),
                blocking_reason=(
                    "execution evidence reference "
                    "could not be validated"
                ),
                notes=(
                    evidence_error,
                ),
                evidence_refs=(
                    observation.evidence_refs
                ),
                analytical_cutoff=(
                    observation.analytical_cutoff
                ),
            )

        if (
            definition.analytical_cutoff_rule
            == "FROZEN_T_REQUIRED"
            and observation.analytical_cutoff
            is None
        ):
            return self._execution_error(
                definition,
                registration,
                error_type=(
                    "MISSING_ANALYTICAL_CUTOFF"
                ),
                blocking_reason=(
                    "FROZEN_T_REQUIRED case "
                    "did not record analytical cutoff"
                ),
                evidence_refs=(
                    observation.evidence_refs
                ),
                notes=(
                    observation.notes
                ),
            )

        passed = all(
            (
                observation
                .expected_conditions_satisfied,
                not observation
                .forbidden_output_observed,
                observation
                .regression_dependencies_satisfied,
                observation
                .rights_constraints_satisfied,
            )
        )

        result_state = (
            HarnessResultState.PASS
            if passed
            else HarnessResultState.FAIL
        )

        return CaseExecutionRecord(
            case_id=case_id,
            execution_state=(
                HarnessExecutionState.EXECUTED
            ),
            result_state=result_state,
            rights_basis=(
                definition.rights_basis
            ),
            analytical_cutoff_rule=(
                definition.analytical_cutoff_rule
            ),
            reference_methodology=(
                definition.reference_methodology
            ),
            executor_id=(
                registration.executor_id
            ),
            executor_version=(
                registration.executor_version
            ),
            executor_reference=(
                registration.executor_reference
            ),
            expected_conditions_satisfied=(
                observation
                .expected_conditions_satisfied
            ),
            forbidden_output_observed=(
                observation
                .forbidden_output_observed
            ),
            regression_dependencies_satisfied=(
                observation
                .regression_dependencies_satisfied
            ),
            rights_constraints_satisfied=(
                observation
                .rights_constraints_satisfied
            ),
            evidence_refs=(
                observation.evidence_refs
            ),
            notes=observation.notes,
            analytical_cutoff=(
                observation.analytical_cutoff
            ),
        )

    def run_all(
        self,
    ) -> tuple[
        CaseExecutionRecord,
        ...
    ]:
        return tuple(
            self.run_case(
                definition.case_id
            )
            for definition
            in self._definitions
        )

    def _execution_error(
        self,
        definition: CanonicalCaseDefinition,
        registration: _ExecutorRegistration,
        *,
        error_type: str,
        blocking_reason: str,
        notes: tuple[str, ...] = (),
        evidence_refs: tuple[
            ExecutionEvidenceReference,
            ...
        ] = (),
        analytical_cutoff: str | None = None,
    ) -> CaseExecutionRecord:
        return CaseExecutionRecord(
            case_id=definition.case_id,
            execution_state=(
                HarnessExecutionState
                .EXECUTION_ERROR
            ),
            result_state=(
                HarnessResultState.NO_RESULT
            ),
            rights_basis=(
                definition.rights_basis
            ),
            analytical_cutoff_rule=(
                definition.analytical_cutoff_rule
            ),
            reference_methodology=(
                definition.reference_methodology
            ),
            executor_id=(
                registration.executor_id
            ),
            executor_version=(
                registration.executor_version
            ),
            executor_reference=(
                registration.executor_reference
            ),
            evidence_refs=evidence_refs,
            notes=notes,
            analytical_cutoff=analytical_cutoff,
            blocking_reason=blocking_reason,
            error_type=error_type,
        )

    def _execution_evidence_error(
        self,
        refs: tuple[
            ExecutionEvidenceReference,
            ...
        ],
        registration: _ExecutorRegistration,
    ) -> str | None:
        if not refs:
            return (
                "no execution evidence references"
            )

        for ref in refs:
            if (
                ref.producer_executor_id
                != registration.executor_id
            ):
                return (
                    f"{ref.evidence_id}: executor "
                    "identity mismatch"
                )

            if (
                ref.producer_executor_version
                != registration.executor_version
            ):
                return (
                    f"{ref.evidence_id}: executor "
                    "version mismatch"
                )

            relative = Path(
                ref.artifact_path
            )

            if relative.is_absolute():
                return (
                    f"{ref.evidence_id}: absolute "
                    "artifact path prohibited"
                )

            candidate = (
                self._repo_root
                / relative
            ).resolve()

            if not candidate.is_relative_to(
                self._repo_root
            ):
                return (
                    f"{ref.evidence_id}: artifact "
                    "path escapes repository root"
                )

            if not candidate.is_file():
                return (
                    f"{ref.evidence_id}: evidence "
                    "artifact not found"
                )

            digest = _sha256_file(
                candidate
            )

            if digest != ref.sha256:
                return (
                    f"{ref.evidence_id}: SHA-256 "
                    "mismatch"
                )

        return None

    def _protected_integrity_error(
        self,
    ) -> str | None:
        current = (
            self._fingerprint_protected_artifacts()
        )

        baseline_paths = set(
            self._protected_baseline
        )

        current_paths = set(
            current
        )

        added = sorted(
            current_paths
            - baseline_paths
        )

        removed = sorted(
            baseline_paths
            - current_paths
        )

        changed = sorted(
            path
            for path
            in baseline_paths
            & current_paths
            if (
                self._protected_baseline[path]
                != current[path]
            )
        )

        if not (
            added
            or removed
            or changed
        ):
            return None

        parts = []

        if added:
            parts.append(
                "added="
                + ",".join(added)
            )

        if removed:
            parts.append(
                "removed="
                + ",".join(removed)
            )

        if changed:
            parts.append(
                "changed="
                + ",".join(changed)
            )

        return "; ".join(parts)

    def _fingerprint_protected_artifacts(
        self,
    ) -> dict[str, str]:
        paths: set[Path] = set()

        project_state = (
            self._repo_root
            / "00_CONTROL"
            / "PROJECT_STATE.md"
        )

        if project_state.is_file():
            paths.add(project_state)

        protected_roots = (
            self._repo_root
            / "01_STAGE0_FOUNDATION",
            self._repo_root
            / "02_BENCHMARKS"
            / "S0_6A",
            self._repo_root
            / "02_BENCHMARKS"
            / "S0_6B",
        )

        for root in protected_roots:
            if not root.exists():
                continue

            for path in root.rglob("*"):
                if path.is_file():
                    paths.add(path)

        return {
            str(
                path.relative_to(
                    self._repo_root
                )
            ): _sha256_file(path)
            for path in sorted(paths)
        }


def _sha256_file(
    path: Path,
) -> str:
    digest = hashlib.sha256()

    with path.open(
        "rb"
    ) as handle:
        while True:
            chunk = handle.read(
                1024 * 1024
            )

            if not chunk:
                break

            digest.update(
                chunk
            )

    return digest.hexdigest()


def _read_csv(
    path: Path,
) -> list[dict[str, str]]:
    if not path.is_file():
        raise BenchmarkContractError(
            f"missing canonical artifact: {path}"
        )

    with path.open(
        encoding="utf-8",
        newline="",
    ) as handle:
        return list(
            csv.DictReader(handle)
        )


def _validate_fields(
    path: Path,
    rows: list[dict[str, str]],
    required: set[str],
) -> None:
    if not rows:
        raise BenchmarkContractError(
            f"empty canonical artifact: {path}"
        )

    actual = set(rows[0])

    missing = required - actual

    if missing:
        raise BenchmarkContractError(
            f"{path}: missing fields "
            + ",".join(sorted(missing))
        )


def _index_rows(
    path: Path,
    rows: list[dict[str, str]],
) -> dict[str, dict[str, str]]:
    indexed: dict[
        str,
        dict[str, str],
    ] = {}

    for row in rows:
        case_id = row.get(
            "case_id",
            "",
        ).strip()

        if not case_id:
            raise BenchmarkContractError(
                f"{path}: blank case_id"
            )

        if case_id in indexed:
            raise BenchmarkContractError(
                f"{path}: duplicate {case_id}"
            )

        indexed[case_id] = row

    return indexed
