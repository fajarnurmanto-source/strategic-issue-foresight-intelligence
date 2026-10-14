from __future__ import annotations

import hashlib
import json
from dataclasses import dataclass, replace
from datetime import datetime
from pathlib import Path
from typing import Callable

from sifi.domain import ObservabilityState, PersistenceClass, TrajectoryDimension
from sifi.issues import (
    IssueEvidenceLinks,
    IssueGeography,
    IssueLineage,
    IssueObject,
    IssueObjectRepository,
    IssueTrajectoryState,
)
from sifi.persistence import SQLiteVersionStore

from .model import CanonicalCaseDefinition, CaseObservation, ExecutionEvidenceReference


MATERIALIZED_BATCH6_CASE_IDS = ("CB21",)


@dataclass(frozen=True, slots=True)
class Batch6ExecutorSpec:
    case_id: str
    executor_id: str
    executor_version: str
    executor_reference: str
    fixture_path: str
    manifest_path: str


BATCH6_EXECUTOR_SPECS = {
    "CB21": Batch6ExecutorSpec(
        case_id="CB21",
        executor_id="SIFI-CB21-HISTORICAL-PIT-EXECUTOR",
        executor_version="v0.1",
        executor_reference="sifi.benchmarks.materialized_cases_batch6:execute_cb21",
        fixture_path="04_IMPLEMENTATION/S1_6D/materialized_cases/CB21.fixture.json",
        manifest_path="04_IMPLEMENTATION/S1_6D/materialized_cases/CB21.materialization.json",
    )
}


def _root() -> Path:
    return Path(__file__).resolve().parents[3]


def _dt(value: str) -> datetime:
    parsed = datetime.fromisoformat(value)
    if parsed.tzinfo is None or parsed.utcoffset() is None:
        raise ValueError("fixture datetime must be timezone-aware")
    return parsed


def _fixture() -> dict:
    spec = BATCH6_EXECUTOR_SPECS["CB21"]
    payload = json.loads((_root() / spec.fixture_path).read_text(encoding="utf-8"))
    if payload.get("case_id") != "CB21":
        raise ValueError("CB21: fixture case binding mismatch")
    return payload


def _base_issue(fixture: dict) -> IssueObject:
    first = fixture["versions"][0]
    trajectory_time = _dt(first["effective_at"])
    return IssueObject(
        issue_id=fixture["issue_id"],
        title=first["title"],
        issue_type="CONTROLLED_PIT_TEST",
        persistence_class=PersistenceClass("PC-01"),
        lifecycle_state=first["lifecycle_state"],
        first_seen=_dt(first["effective_at"]),
        last_updated=_dt(first["effective_at"]),
        identity_review_state="REVIEW_PENDING",
        evidence=IssueEvidenceLinks(supporting_evidence_ids=(first["evidence_id"],)),
        geography=IssueGeography(relevance_geography=("SYNTHETIC",)),
        trajectories=tuple(
            IssueTrajectoryState(
                dimension=dimension,
                trajectory_state="PRE_CUTOFF_BOUNDED",
                as_of=trajectory_time,
                evidence_ids=(first["evidence_id"],),
            )
            for dimension in TrajectoryDimension
        ),
        observability_states=(ObservabilityState("OBS-05"),),
        uncertainty_notes=("Future information excluded at frozen cutoff.",),
        lineage=IssueLineage(),
    )


def execute_cb21(definition: CanonicalCaseDefinition) -> CaseObservation:
    if getattr(definition, "case_id", None) != "CB21":
        raise ValueError("CB21: executor received wrong case definition")

    fixture = _fixture()
    cutoff = _dt(fixture["frozen_cutoff"])
    base = _base_issue(fixture)

    with SQLiteVersionStore(":memory:") as store:
        repository = IssueObjectRepository(store)
        for item in fixture["versions"]:
            issue = replace(
                base,
                title=item["title"],
                lifecycle_state=item["lifecycle_state"],
                last_updated=_dt(item["effective_at"]),
                evidence=IssueEvidenceLinks(
                    supporting_evidence_ids=(item["evidence_id"],)
                ),
            )
            repository.append(
                issue,
                version=item["version"],
                effective_at=_dt(item["effective_at"]),
                recorded_at=_dt(item["recorded_at"]),
            )

        historical = repository.as_of(fixture["issue_id"], cutoff)
        latest = repository.latest(fixture["issue_id"])
        history = repository.history(fixture["issue_id"])

    expected = (
        historical is not None
        and historical.version == fixture["expected_version_at_cutoff"]
        and historical.issue.title == fixture["expected_title_at_cutoff"]
        and historical.issue.lifecycle_state
        == fixture["expected_lifecycle_at_cutoff"]
    )
    future_leakage = (
        historical is None
        or historical.issue.title in fixture["future_only_titles"]
        or historical.issue.lifecycle_state in fixture["future_only_lifecycle_states"]
        or any(
            evidence_id in fixture["future_only_evidence_ids"]
            for evidence_id in historical.issue.evidence.supporting_evidence_ids
        )
    )
    regression = (
        latest is not None
        and latest.version == len(fixture["versions"])
        and tuple(item.version for item in history)
        == tuple(range(1, len(fixture["versions"]) + 1))
    )

    spec = BATCH6_EXECUTOR_SPECS["CB21"]
    fixture_path = _root() / spec.fixture_path
    evidence_ref = ExecutionEvidenceReference(
        evidence_id="CB21-MATERIALIZED-FIXTURE",
        evidence_kind="CANONICAL_FIXTURE_MATERIALIZATION",
        artifact_path=spec.fixture_path,
        sha256=hashlib.sha256(fixture_path.read_bytes()).hexdigest(),
        producer_executor_id=spec.executor_id,
        producer_executor_version=spec.executor_version,
    )
    return CaseObservation(
        expected_conditions_satisfied=expected,
        forbidden_output_observed=future_leakage,
        regression_dependencies_satisfied=regression,
        rights_constraints_satisfied=True,
        evidence_refs=(evidence_ref,),
        notes=(
            "frozen_cutoff=" + fixture["frozen_cutoff"],
            "historical_version=" + str(historical.version if historical else 0),
            "latest_version=" + str(latest.version if latest else 0),
            "future_information_leakage=" + str(future_leakage).lower(),
            "append_only_history_preserved=" + str(regression).lower(),
        ),
        analytical_cutoff=fixture["frozen_cutoff"],
    )


BATCH6_EXECUTORS: dict[str, Callable[[CanonicalCaseDefinition], CaseObservation]] = {
    "CB21": execute_cb21
}
