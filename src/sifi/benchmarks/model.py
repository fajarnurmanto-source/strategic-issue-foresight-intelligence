from __future__ import annotations

import re
from dataclasses import dataclass
from enum import StrEnum


_SHA256_RE = re.compile(
    r"^[0-9a-f]{64}$"
)


class HarnessExecutionState(StrEnum):
    NOT_READY_FOR_EXECUTION = (
        "NOT_READY_FOR_EXECUTION"
    )
    EXECUTED = "EXECUTED"
    BLOCKED_BY_MISSING_CAPABILITY = (
        "BLOCKED_BY_MISSING_CAPABILITY"
    )
    PROTOCOL_ONLY = "PROTOCOL_ONLY"
    EXECUTION_ERROR = "EXECUTION_ERROR"


class HarnessResultState(StrEnum):
    PASS = "PASS"
    FAIL = "FAIL"
    NO_RESULT = "NO_RESULT"


def _require_text(
    name: str,
    value: str,
) -> None:
    if not isinstance(value, str) or not value.strip():
        raise ValueError(
            f"{name} must be non-empty"
        )


def _require_unique_strings(
    name: str,
    values: tuple[str, ...],
) -> None:
    if len(values) != len(set(values)):
        raise ValueError(
            f"{name} contains duplicates"
        )

    for value in values:
        _require_text(
            name,
            value,
        )


@dataclass(frozen=True, slots=True)
class ExecutionEvidenceReference:
    evidence_id: str
    evidence_kind: str
    artifact_path: str
    sha256: str

    producer_executor_id: str
    producer_executor_version: str

    def __post_init__(self) -> None:
        for name in (
            "evidence_id",
            "evidence_kind",
            "artifact_path",
            "producer_executor_id",
            "producer_executor_version",
        ):
            _require_text(
                name,
                getattr(self, name),
            )

        if not isinstance(
            self.sha256,
            str,
        ):
            raise ValueError(
                "sha256 must be a string"
            )

        normalized = self.sha256.lower()

        if not _SHA256_RE.fullmatch(
            normalized
        ):
            raise ValueError(
                "sha256 must be 64 lowercase "
                "hexadecimal characters"
            )

        object.__setattr__(
            self,
            "sha256",
            normalized,
        )


@dataclass(frozen=True, slots=True)
class CanonicalCaseDefinition:
    case_id: str
    title: str
    legacy_refs: str
    domain: str

    registry_materialization_state: str
    registry_execution_state: str
    registry_result_state: str

    rights_basis: str
    analytical_cutoff_rule: str
    reference_methodology: str

    benchmark_input_reference: str
    expected_output_reference: str
    forbidden_output_reference: str
    acceptance_reference: str
    failure_condition: str
    contract_coverage_reference: str
    regression_dependency_reference: str

    control_materialization_state: str
    control_execution_state: str
    control_result_state: str

    def __post_init__(self) -> None:
        for name in (
            "case_id",
            "title",
            "domain",
            "registry_materialization_state",
            "registry_execution_state",
            "registry_result_state",
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
            "control_materialization_state",
            "control_execution_state",
            "control_result_state",
        ):
            _require_text(
                name,
                getattr(self, name),
            )

    @property
    def protocol_only(self) -> bool:
        return (
            self.registry_materialization_state
            == "PROTOCOL_ONLY"
            or
            self.control_materialization_state
            == "PROTOCOL_ONLY"
        )


@dataclass(frozen=True, slots=True)
class CaseObservation:
    expected_conditions_satisfied: bool
    forbidden_output_observed: bool
    regression_dependencies_satisfied: bool
    rights_constraints_satisfied: bool

    evidence_refs: tuple[
        ExecutionEvidenceReference,
        ...
    ]

    notes: tuple[str, ...] = ()
    analytical_cutoff: str | None = None

    def __post_init__(self) -> None:
        if not self.evidence_refs:
            raise ValueError(
                "actual benchmark execution "
                "requires execution evidence"
            )

        for ref in self.evidence_refs:
            if not isinstance(
                ref,
                ExecutionEvidenceReference,
            ):
                raise ValueError(
                    "evidence_refs must contain "
                    "ExecutionEvidenceReference"
                )

        evidence_ids = tuple(
            ref.evidence_id
            for ref in self.evidence_refs
        )

        if (
            len(evidence_ids)
            != len(set(evidence_ids))
        ):
            raise ValueError(
                "duplicate evidence_id"
            )

        _require_unique_strings(
            "notes",
            self.notes,
        )

        if self.analytical_cutoff is not None:
            _require_text(
                "analytical_cutoff",
                self.analytical_cutoff,
            )


@dataclass(frozen=True, slots=True)
class CaseExecutionRecord:
    case_id: str
    execution_state: HarnessExecutionState
    result_state: HarnessResultState

    rights_basis: str
    analytical_cutoff_rule: str
    reference_methodology: str

    executor_id: str | None = None
    executor_version: str | None = None
    executor_reference: str | None = None

    expected_conditions_satisfied: bool | None = None
    forbidden_output_observed: bool | None = None
    regression_dependencies_satisfied: bool | None = None
    rights_constraints_satisfied: bool | None = None

    evidence_refs: tuple[
        ExecutionEvidenceReference,
        ...
    ] = ()

    notes: tuple[str, ...] = ()
    analytical_cutoff: str | None = None

    blocking_reason: str | None = None
    error_type: str | None = None

    def __post_init__(self) -> None:
        _require_text(
            "case_id",
            self.case_id,
        )
        _require_text(
            "rights_basis",
            self.rights_basis,
        )
        _require_text(
            "analytical_cutoff_rule",
            self.analytical_cutoff_rule,
        )
        _require_text(
            "reference_methodology",
            self.reference_methodology,
        )

        _require_unique_strings(
            "notes",
            self.notes,
        )

        for ref in self.evidence_refs:
            if not isinstance(
                ref,
                ExecutionEvidenceReference,
            ):
                raise ValueError(
                    "invalid execution evidence "
                    "reference"
                )

        attempted = (
            self.execution_state
            in {
                HarnessExecutionState.EXECUTED,
                HarnessExecutionState.EXECUTION_ERROR,
            }
        )

        executor_values = (
            self.executor_id,
            self.executor_version,
            self.executor_reference,
        )

        if attempted:
            if any(
                value is None
                for value in executor_values
            ):
                raise ValueError(
                    "execution attempt requires "
                    "executor identity"
                )

            for name, value in zip(
                (
                    "executor_id",
                    "executor_version",
                    "executor_reference",
                ),
                executor_values,
            ):
                _require_text(
                    name,
                    value,
                )
        else:
            if any(
                value is not None
                for value in executor_values
            ):
                raise ValueError(
                    "non-attempted case cannot "
                    "claim executor identity"
                )

        if (
            self.execution_state
            == HarnessExecutionState.EXECUTED
        ):
            if self.result_state not in {
                HarnessResultState.PASS,
                HarnessResultState.FAIL,
            }:
                raise ValueError(
                    "executed case must have "
                    "PASS or FAIL"
                )

            if not self.evidence_refs:
                raise ValueError(
                    "executed case requires "
                    "validated evidence refs"
                )

            required = (
                self.expected_conditions_satisfied,
                self.forbidden_output_observed,
                self.regression_dependencies_satisfied,
                self.rights_constraints_satisfied,
            )

            if any(
                value is None
                for value in required
            ):
                raise ValueError(
                    "executed case requires all "
                    "acceptance dimensions"
                )

        else:
            if (
                self.result_state
                != HarnessResultState.NO_RESULT
            ):
                raise ValueError(
                    "non-executed case cannot "
                    "have PASS or FAIL"
                )

        if self.analytical_cutoff is not None:
            _require_text(
                "analytical_cutoff",
                self.analytical_cutoff,
            )

        if self.blocking_reason is not None:
            _require_text(
                "blocking_reason",
                self.blocking_reason,
            )

        if self.error_type is not None:
            _require_text(
                "error_type",
                self.error_type,
            )
