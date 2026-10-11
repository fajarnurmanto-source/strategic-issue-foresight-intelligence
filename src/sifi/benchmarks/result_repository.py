from __future__ import annotations

import hashlib
import json
import re
from dataclasses import dataclass
from datetime import datetime
from typing import Any

from sifi.domain.versioning import (
    VersionedSnapshot,
    require_aware_datetime,
)
from sifi.persistence import SQLiteVersionStore

from .model import (
    CaseExecutionRecord,
    ExecutionEvidenceReference,
    HarnessExecutionState,
    HarnessResultState,
)


BENCHMARK_RESULT_OBJECT_TYPE = (
    "BENCHMARK_RESULT"
)

_SHA256_RE = re.compile(
    r"^[0-9a-f]{64}$"
)


class BenchmarkResultRepositoryError(
    Exception
):
    pass


class BenchmarkResultRecordingError(
    BenchmarkResultRepositoryError
):
    pass


class BenchmarkResultVersionSequenceError(
    BenchmarkResultRepositoryError
):
    pass


class DuplicateExecutionResultError(
    BenchmarkResultRepositoryError
):
    pass


def _require_sha256(
    name: str,
    value: str,
) -> str:
    if (
        not isinstance(value, str)
        or not _SHA256_RE.fullmatch(value)
    ):
        raise ValueError(
            f"{name} must be 64 lowercase "
            "hexadecimal characters"
        )

    return value


def _evidence_payload(
    ref: ExecutionEvidenceReference,
) -> dict[str, str]:
    return {
        "evidence_id": ref.evidence_id,
        "evidence_kind": ref.evidence_kind,
        "artifact_path": ref.artifact_path,
        "sha256": ref.sha256,
        "producer_executor_id": (
            ref.producer_executor_id
        ),
        "producer_executor_version": (
            ref.producer_executor_version
        ),
    }


def execution_record_payload(
    execution: CaseExecutionRecord,
) -> dict[str, Any]:
    return {
        "case_id": execution.case_id,
        "execution_state": (
            execution.execution_state.value
        ),
        "result_state": (
            execution.result_state.value
        ),
        "rights_basis": (
            execution.rights_basis
        ),
        "analytical_cutoff_rule": (
            execution.analytical_cutoff_rule
        ),
        "reference_methodology": (
            execution.reference_methodology
        ),
        "executor_id": execution.executor_id,
        "executor_version": (
            execution.executor_version
        ),
        "executor_reference": (
            execution.executor_reference
        ),
        "authorization_id": (
            execution.authorization_id
        ),
        "contract_fingerprint": (
            execution.contract_fingerprint
        ),
        "expected_conditions_satisfied": (
            execution
            .expected_conditions_satisfied
        ),
        "forbidden_output_observed": (
            execution.forbidden_output_observed
        ),
        "regression_dependencies_satisfied": (
            execution
            .regression_dependencies_satisfied
        ),
        "rights_constraints_satisfied": (
            execution.rights_constraints_satisfied
        ),
        "evidence_refs": [
            _evidence_payload(ref)
            for ref in execution.evidence_refs
        ],
        "notes": list(
            execution.notes
        ),
        "analytical_cutoff": (
            execution.analytical_cutoff
        ),
        "blocking_reason": (
            execution.blocking_reason
        ),
        "error_type": execution.error_type,
    }


def execution_record_fingerprint(
    execution: CaseExecutionRecord,
) -> str:
    encoded = json.dumps(
        execution_record_payload(
            execution
        ),
        ensure_ascii=False,
        sort_keys=True,
        separators=(",", ":"),
    ).encode(
        "utf-8"
    )

    return hashlib.sha256(
        encoded
    ).hexdigest()


@dataclass(frozen=True, slots=True)
class RecordedBenchmarkResult:
    case_id: str
    result_state: HarnessResultState

    execution_fingerprint: str
    contract_fingerprint: str
    authorization_id: str

    executor_id: str
    executor_version: str
    executor_reference: str

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
        if not self.case_id.strip():
            raise ValueError(
                "case_id must be non-empty"
            )

        if self.result_state not in {
            HarnessResultState.PASS,
            HarnessResultState.FAIL,
        }:
            raise ValueError(
                "recorded benchmark result "
                "must be PASS or FAIL"
            )

        _require_sha256(
            "execution_fingerprint",
            self.execution_fingerprint,
        )

        _require_sha256(
            "contract_fingerprint",
            self.contract_fingerprint,
        )

        for name in (
            "authorization_id",
            "executor_id",
            "executor_version",
            "executor_reference",
        ):
            value = getattr(
                self,
                name,
            )

            if (
                not isinstance(value, str)
                or not value.strip()
            ):
                raise ValueError(
                    f"{name} must be non-empty"
                )

        if not self.evidence_refs:
            raise ValueError(
                "recorded result requires "
                "execution evidence refs"
            )

    @property
    def recording_state(self) -> str:
        return "RESULT_RECORDED"

    def to_payload(
        self,
    ) -> dict[str, Any]:
        return {
            "recording_state": (
                self.recording_state
            ),
            "case_id": self.case_id,
            "result_state": (
                self.result_state.value
            ),
            "execution_fingerprint": (
                self.execution_fingerprint
            ),
            "contract_fingerprint": (
                self.contract_fingerprint
            ),
            "authorization_id": (
                self.authorization_id
            ),
            "executor_id": self.executor_id,
            "executor_version": (
                self.executor_version
            ),
            "executor_reference": (
                self.executor_reference
            ),
            "expected_conditions_satisfied": (
                self
                .expected_conditions_satisfied
            ),
            "forbidden_output_observed": (
                self.forbidden_output_observed
            ),
            "regression_dependencies_satisfied": (
                self
                .regression_dependencies_satisfied
            ),
            "rights_constraints_satisfied": (
                self.rights_constraints_satisfied
            ),
            "evidence_refs": [
                _evidence_payload(ref)
                for ref in self.evidence_refs
            ],
            "notes": list(
                self.notes
            ),
            "analytical_cutoff": (
                self.analytical_cutoff
            ),
        }

    @classmethod
    def from_payload(
        cls,
        payload: dict[str, Any],
    ) -> "RecordedBenchmarkResult":
        if (
            payload.get(
                "recording_state"
            )
            != "RESULT_RECORDED"
        ):
            raise ValueError(
                "payload is not a "
                "RESULT_RECORDED benchmark result"
            )

        refs = tuple(
            ExecutionEvidenceReference(
                evidence_id=item[
                    "evidence_id"
                ],
                evidence_kind=item[
                    "evidence_kind"
                ],
                artifact_path=item[
                    "artifact_path"
                ],
                sha256=item["sha256"],
                producer_executor_id=item[
                    "producer_executor_id"
                ],
                producer_executor_version=item[
                    "producer_executor_version"
                ],
            )
            for item in payload[
                "evidence_refs"
            ]
        )

        return cls(
            case_id=payload["case_id"],
            result_state=(
                HarnessResultState(
                    payload["result_state"]
                )
            ),
            execution_fingerprint=payload[
                "execution_fingerprint"
            ],
            contract_fingerprint=payload[
                "contract_fingerprint"
            ],
            authorization_id=payload[
                "authorization_id"
            ],
            executor_id=payload[
                "executor_id"
            ],
            executor_version=payload[
                "executor_version"
            ],
            executor_reference=payload[
                "executor_reference"
            ],
            expected_conditions_satisfied=(
                payload[
                    "expected_conditions_satisfied"
                ]
            ),
            forbidden_output_observed=(
                payload[
                    "forbidden_output_observed"
                ]
            ),
            regression_dependencies_satisfied=(
                payload[
                    "regression_dependencies_satisfied"
                ]
            ),
            rights_constraints_satisfied=(
                payload[
                    "rights_constraints_satisfied"
                ]
            ),
            evidence_refs=refs,
            notes=tuple(
                payload.get(
                    "notes",
                    (),
                )
            ),
            analytical_cutoff=payload.get(
                "analytical_cutoff"
            ),
        )


@dataclass(frozen=True, slots=True)
class PersistedBenchmarkResultVersion:
    version: int
    effective_at: datetime
    recorded_at: datetime
    result: RecordedBenchmarkResult


class BenchmarkResultRepository:
    """Append-only canonical benchmark result recorder.

    This adapter reuses the S1.1 SQLiteVersionStore.

    run_case() creates execution evidence only.
    record() is the explicit RESULT_RECORDED boundary.
    """

    def __init__(
        self,
        store: SQLiteVersionStore,
        *,
        expected_contract_fingerprint: str,
    ) -> None:
        self._store = store
        self._expected_contract_fingerprint = (
            _require_sha256(
                "expected_contract_fingerprint",
                expected_contract_fingerprint,
            )
        )

    def record(
        self,
        execution: CaseExecutionRecord,
        *,
        version: int,
        effective_at: datetime,
        recorded_at: datetime,
    ) -> PersistedBenchmarkResultVersion:
        require_aware_datetime(
            effective_at,
            "effective_at",
        )

        require_aware_datetime(
            recorded_at,
            "recorded_at",
        )

        if (
            execution.execution_state
            != HarnessExecutionState.EXECUTED
        ):
            raise BenchmarkResultRecordingError(
                "only an EXECUTED case can "
                "enter RESULT_RECORDED"
            )

        if (
            execution.result_state
            != HarnessResultState.NO_RESULT
        ):
            raise BenchmarkResultRecordingError(
                "execution record must remain "
                "NO_RESULT before recording"
            )

        if (
            execution.contract_fingerprint
            != self._expected_contract_fingerprint
        ):
            raise BenchmarkResultRecordingError(
                "execution contract fingerprint "
                "does not match result repository "
                "contract binding"
            )

        required = (
            execution
            .expected_conditions_satisfied,
            execution
            .forbidden_output_observed,
            execution
            .regression_dependencies_satisfied,
            execution
            .rights_constraints_satisfied,
        )

        if any(
            value is None
            for value in required
        ):
            raise BenchmarkResultRecordingError(
                "execution acceptance dimensions "
                "are incomplete"
            )

        fingerprint = (
            execution_record_fingerprint(
                execution
            )
        )

        for prior in self.history(
            execution.case_id
        ):
            if (
                prior.result
                .execution_fingerprint
                == fingerprint
            ):
                raise DuplicateExecutionResultError(
                    "the same execution cannot be "
                    "recorded more than once"
                )

        latest = self._store.latest(
            BENCHMARK_RESULT_OBJECT_TYPE,
            execution.case_id,
        )

        expected_version = (
            1
            if latest is None
            else latest.version + 1
        )

        if version != expected_version:
            raise BenchmarkResultVersionSequenceError(
                "expected benchmark result "
                f"version {expected_version}, "
                f"received {version}"
            )

        passed = all(
            (
                execution
                .expected_conditions_satisfied,
                not execution
                .forbidden_output_observed,
                execution
                .regression_dependencies_satisfied,
                execution
                .rights_constraints_satisfied,
            )
        )

        state = (
            HarnessResultState.PASS
            if passed
            else HarnessResultState.FAIL
        )

        result = RecordedBenchmarkResult(
            case_id=execution.case_id,
            result_state=state,
            execution_fingerprint=(
                fingerprint
            ),
            contract_fingerprint=(
                execution.contract_fingerprint
            ),
            authorization_id=(
                execution.authorization_id
            ),
            executor_id=(
                execution.executor_id
            ),
            executor_version=(
                execution.executor_version
            ),
            executor_reference=(
                execution.executor_reference
            ),
            expected_conditions_satisfied=(
                execution
                .expected_conditions_satisfied
            ),
            forbidden_output_observed=(
                execution
                .forbidden_output_observed
            ),
            regression_dependencies_satisfied=(
                execution
                .regression_dependencies_satisfied
            ),
            rights_constraints_satisfied=(
                execution
                .rights_constraints_satisfied
            ),
            evidence_refs=(
                execution.evidence_refs
            ),
            notes=execution.notes,
            analytical_cutoff=(
                execution.analytical_cutoff
            ),
        )

        self._store.append(
            VersionedSnapshot(
                object_type=(
                    BENCHMARK_RESULT_OBJECT_TYPE
                ),
                object_id=(
                    execution.case_id
                ),
                version=version,
                effective_at=effective_at,
                recorded_at=recorded_at,
                payload=result.to_payload(),
            )
        )

        return PersistedBenchmarkResultVersion(
            version=version,
            effective_at=effective_at,
            recorded_at=recorded_at,
            result=result,
        )

    def get(
        self,
        case_id: str,
        version: int,
    ) -> PersistedBenchmarkResultVersion | None:
        snapshot = self._store.get(
            BENCHMARK_RESULT_OBJECT_TYPE,
            case_id,
            version,
        )

        return (
            self._decode(snapshot)
            if snapshot is not None
            else None
        )

    def latest(
        self,
        case_id: str,
    ) -> PersistedBenchmarkResultVersion | None:
        snapshot = self._store.latest(
            BENCHMARK_RESULT_OBJECT_TYPE,
            case_id,
        )

        return (
            self._decode(snapshot)
            if snapshot is not None
            else None
        )

    def as_of(
        self,
        case_id: str,
        cutoff: datetime,
    ) -> PersistedBenchmarkResultVersion | None:
        snapshot = self._store.as_of(
            BENCHMARK_RESULT_OBJECT_TYPE,
            case_id,
            cutoff,
        )

        return (
            self._decode(snapshot)
            if snapshot is not None
            else None
        )

    def history(
        self,
        case_id: str,
    ) -> tuple[
        PersistedBenchmarkResultVersion,
        ...
    ]:
        return tuple(
            self._decode(snapshot)
            for snapshot in self._store.history(
                BENCHMARK_RESULT_OBJECT_TYPE,
                case_id,
            )
        )

    @staticmethod
    def _decode(
        snapshot: VersionedSnapshot,
    ) -> PersistedBenchmarkResultVersion:
        return PersistedBenchmarkResultVersion(
            version=snapshot.version,
            effective_at=(
                snapshot.effective_at
            ),
            recorded_at=(
                snapshot.recorded_at
            ),
            result=(
                RecordedBenchmarkResult
                .from_payload(
                    dict(
                        snapshot.payload
                    )
                )
            ),
        )
