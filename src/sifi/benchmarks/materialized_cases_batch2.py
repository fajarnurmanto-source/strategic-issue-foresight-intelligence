from __future__ import annotations

import hashlib
import json
from dataclasses import dataclass
from pathlib import Path
from typing import Callable

from .model import (
    CanonicalCaseDefinition,
    CaseObservation,
    ExecutionEvidenceReference,
)


MATERIALIZED_BATCH2_CASE_IDS = (
    "CB02",
)


@dataclass(frozen=True, slots=True)
class Batch2ExecutorSpec:
    case_id: str
    executor_id: str
    executor_version: str
    executor_reference: str
    fixture_path: str
    manifest_path: str


BATCH2_EXECUTOR_SPECS = {
    "CB02": Batch2ExecutorSpec(
        case_id="CB02",
        executor_id="SIFI-CB02-ATTENTION-IMPACT-SEPARATION-EXECUTOR",
        executor_version="v0.1",
        executor_reference=(
            "sifi.benchmarks.materialized_cases_batch2:execute_cb02"
        ),
        fixture_path=(
            "04_IMPLEMENTATION/S1_6D/materialized_cases/CB02.fixture.json"
        ),
        manifest_path=(
            "04_IMPLEMENTATION/S1_6D/materialized_cases/"
            "CB02.materialization.json"
        ),
    ),
}


def _repo_root() -> Path:
    return Path(__file__).resolve().parents[3]


def _sha256(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def _load_fixture(case_id: str) -> dict:
    spec = BATCH2_EXECUTOR_SPECS[case_id]
    path = _repo_root() / spec.fixture_path
    payload = json.loads(path.read_text(encoding="utf-8"))

    if payload.get("case_id") != case_id:
        raise ValueError(case_id + ": fixture case binding mismatch")

    return payload


def _fixture_reference(case_id: str) -> ExecutionEvidenceReference:
    spec = BATCH2_EXECUTOR_SPECS[case_id]
    path = _repo_root() / spec.fixture_path

    return ExecutionEvidenceReference(
        evidence_id=case_id + "-MATERIALIZED-FIXTURE",
        evidence_kind="CANONICAL_FIXTURE_MATERIALIZATION",
        artifact_path=spec.fixture_path,
        sha256=_sha256(path),
        producer_executor_id=spec.executor_id,
        producer_executor_version=spec.executor_version,
    )


def execute_cb02(
    definition: CanonicalCaseDefinition,
) -> CaseObservation:
    """Verify high attention without manufacturing impact or severity."""

    if getattr(definition, "case_id", None) != "CB02":
        raise ValueError("CB02: executor received wrong case definition")

    fixture = _load_fixture("CB02")

    publication_count = fixture["observed_publication_count"]
    attention_threshold = fixture["high_attention_threshold"]
    qualified_impact_evidence = tuple(
        fixture["qualified_impact_evidence"]
    )

    high_attention = publication_count >= attention_threshold
    impact_established = bool(qualified_impact_evidence)

    expected = (
        high_attention
        and not impact_established
        and fixture["expected_attention_state"] == "HIGH_ATTENTION"
        and fixture["expected_impact_state"] == "IMPACT_NOT_ESTABLISHED"
    )

    forbidden = (
        fixture["automatic_severity_assigned"]
        or fixture["automatic_strategic_importance_assigned"]
        or impact_established
    )

    regression = (
        fixture["attention_impact_separation_required"] is True
        and fixture["severity_engine_claimed_available"] is False
    )

    rights = (
        fixture["rights_basis"] == "SYNTHETIC_NO_EXTERNAL_RIGHTS"
    )

    return CaseObservation(
        expected_conditions_satisfied=expected,
        forbidden_output_observed=forbidden,
        regression_dependencies_satisfied=regression,
        rights_constraints_satisfied=rights,
        evidence_refs=(_fixture_reference("CB02"),),
        notes=(
            "publication_count=" + str(publication_count),
            "high_attention=" + str(high_attention).upper(),
            "qualified_impact_evidence_count="
            + str(len(qualified_impact_evidence)),
            "impact_established=" + str(impact_established).upper(),
            "automatic_severity_assigned="
            + str(fixture["automatic_severity_assigned"]).upper(),
        ),
        analytical_cutoff=fixture["analytical_cutoff"],
    )


BATCH2_EXECUTORS: dict[
    str,
    Callable[[CanonicalCaseDefinition], CaseObservation],
] = {
    "CB02": execute_cb02,
}
